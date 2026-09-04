<?php

namespace App\Domain\Manufacturing;

use App\Models\Batch;

/**
 * Walks the batch-consumption graph to build a batch's full lineage — backward
 * (what it was made from) and forward (where it was used). Depth-bounded and
 * cycle-safe. Read-only.
 *
 * @phpstan-type TraceRow array{batch: Batch, quantity: string, depth: int}
 */
final class BatchTrace
{
    private const MAX_DEPTH = 20;

    /**
     * Origin lineage — the input batches consumed to make this batch, recursively.
     *
     * @return list<TraceRow>
     */
    public static function origin(Batch $batch): array
    {
        $rows = [];
        self::walk($batch, 'consumedBatches', 0, [$batch->getKey()], $rows);

        return $rows;
    }

    /**
     * Usage lineage — the batches this one was consumed into, recursively.
     *
     * @return list<TraceRow>
     */
    public static function usage(Batch $batch): array
    {
        $rows = [];
        self::walk($batch, 'usedInBatches', 0, [$batch->getKey()], $rows);

        return $rows;
    }

    /**
     * @param  list<int>  $visited
     * @param  list<array{batch: Batch, quantity: string, depth: int}>  $rows
     */
    private static function walk(Batch $batch, string $relation, int $depth, array $visited, array &$rows): void
    {
        if ($depth >= self::MAX_DEPTH) {
            return;
        }

        foreach ($batch->{$relation}()->with('product')->get() as $related) {
            if (in_array($related->getKey(), $visited, true)) {
                continue; // guard against cycles
            }

            $rows[] = [
                'batch' => $related,
                'quantity' => (string) $related->pivot->quantity,
                'depth' => $depth,
            ];

            self::walk($related, $relation, $depth + 1, [...$visited, $related->getKey()], $rows);
        }
    }
}
