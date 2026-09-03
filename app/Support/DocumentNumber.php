<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Atomic, per-company document-number generator. Replaces the racy
 * `COUNT(*) + 1` pattern: two concurrent creates can never mint the same number
 * because the counter is incremented in a single atomic upsert.
 *
 * The `$seed` (usually the current row count) only initialises a brand-new
 * counter; GREATEST() also lets an existing counter self-heal if rows were ever
 * inserted directly (imports, seeders) ahead of the sequence.
 */
final class DocumentNumber
{
    /**
     * Return the next formatted number for a document type, e.g. "SO-0007".
     *
     * @param  string  $key     Document type, unique per company (e.g. "sales_order").
     * @param  string  $prefix  Printed prefix (e.g. "SO-").
     * @param  int     $seed    Current count for this type; seeds/heals the counter.
     * @param  int     $pad     Zero-padding width for the numeric part.
     */
    public static function next(string $key, string $prefix, int $seed, int $pad = 4): string
    {
        $companyId = app(CompanyContext::class)->currentId();

        $row = DB::selectOne(
            'INSERT INTO document_sequences (company_id, "key", value, created_at, updated_at)
             VALUES (?, ?, ?, now(), now())
             ON CONFLICT (company_id, "key")
             DO UPDATE SET value = GREATEST(document_sequences.value + 1, EXCLUDED.value), updated_at = now()
             RETURNING value',
            [$companyId, $key, $seed + 1],
        );

        return $prefix.str_pad((string) $row->value, $pad, '0', STR_PAD_LEFT);
    }
}
