<?php

namespace App\Services\Gemini;

use App\Models\Company;
use App\Services\Gemini\Exceptions\GeminiException;
use App\Support\CompanyContext;

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

        return $this->client->chat($this->systemInstruction($company), $history);
    }

    private function systemInstruction(Company $company): string
    {
        $snapshot = $this->snapshot->for($company);

        return <<<PROMPT
        You are the built-in AI assistant for "{$company->name}", a Bangladeshi trading/manufacturing ERP.

        RULES:
        - Answer ONLY from the DATA SNAPSHOT below and general business knowledge. Do not invent figures.
        - If the answer is not in the snapshot, say so plainly and suggest which report to open (e.g. "Sales Register", "Receivables Aging", "Stock Valuation").
        - You are READ-ONLY. You cannot create, edit, post, or delete anything. If asked to perform an action, explain how to do it in the ERP instead.
        - Format money with the ৳ symbol. Be concise and use short bullet lists where helpful.
        - The snapshot reflects the moment it was generated; for a specific historical period, tell the user to open the matching report with a date filter.

        DATA SNAPSHOT (live, company-scoped):
        {$snapshot}
        PROMPT;
    }
}
