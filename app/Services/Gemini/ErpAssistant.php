<?php

namespace App\Services\Gemini;

use App\Models\Company;
use App\Services\Gemini\Exceptions\GeminiException;
use App\Support\CompanyContext;
use Illuminate\Support\Facades\Cache;

/**
 * High-level AI assistant: assembles the grounding snapshot and behaviour rules,
 * then asks {@see GeminiClient} for a reply. Strictly read-only — it never
 * writes to the ERP; it can only describe figures that already exist.
 */
class ErpAssistant
{
    public function __construct(
        private GeminiClient $client,
        private ErpDataSnapshot $snapshot,
        private CompanyContext $context,
    ) {}

    public function isAvailable(): bool
    {
        return $this->client->isConfigured() && $this->context->has();
    }

    /**
     * Answer the latest user message given prior turns.
     *
     * @param  list<array{role: string, text: string}>  $history  Includes the newest user turn last.
     */
    public function reply(array $history): string
    {
        $company = $this->context->current();

        if (! $company instanceof Company) {
            throw new GeminiException('No active company — open the ERP with a company selected.');
        }

        return $this->sanitize($this->client->chat($this->systemInstruction($company), $history));
    }

    /**
     * Strip Markdown artefacts the chat panel would show literally (it renders
     * plain text), so replies always read as clean, professional prose.
     */
    private function sanitize(string $text): string
    {
        // Remove bold/italic emphasis markers: **text**, __text__, then stray * / _.
        $text = (string) preg_replace('/(\*\*|__)(.+?)\1/s', '$2', $text);
        // Normalise Markdown bullet markers (* or -) at line start to a bullet dot.
        $text = (string) preg_replace('/^[ \t]*[\*\-][ \t]+/m', '• ', $text);
        // Drop Markdown heading hashes and inline code backticks.
        $text = (string) preg_replace('/^[ \t]*#{1,6}[ \t]*/m', '', $text);
        $text = str_replace('`', '', $text);

        return trim($text);
    }

    /** Snapshot cache TTL in seconds — short enough to stay fresh-feeling. */
    private const SNAPSHOT_TTL = 300;

    private function systemInstruction(Company $company): string
    {
        // Reuse a recent snapshot instead of re-running P&L / aging / valuation
        // for every message. Keyed per company so multi-tenant scopes hold.
        $snapshot = Cache::remember(
            "erp.ai.snapshot.company:{$company->getKey()}",
            self::SNAPSHOT_TTL,
            fn (): string => $this->snapshot->for($company),
        );

        return <<<PROMPT
        You are the built-in AI assistant for "{$company->name}", a Bangladeshi trading/manufacturing ERP.

        RULES:
        - Answer ONLY from the DATA SNAPSHOT below and general business knowledge. Never invent or estimate figures.
        - Write in clear, formal, professional business English. Keep replies concise and courteous; avoid slang, emojis, and exclamation marks.
        - Do NOT use Markdown or any formatting symbols — no asterisks (* or **), no hash headings (#), no backticks. The chat shows plain text, so such symbols appear literally. When listing items, put each on its own line beginning with "- ".
        - If the answer is not in the snapshot, say so plainly and direct the user to the relevant report (for example, Sales Register, Receivables Aging, or Stock Valuation).
        - You are READ-ONLY. You cannot create, edit, post, or delete anything. If asked to perform an action, explain the steps to do it within the ERP instead.
        - Format monetary values with the ৳ symbol (for example, ৳1,25,000).
        - The snapshot reflects the moment it was generated; for a specific historical period, ask the user to open the matching report with a date filter.

        DATA SNAPSHOT (live, company-scoped):
        {$snapshot}
        PROMPT;
    }
}
