<?php

namespace App\Actions\Sales;

use App\Enums\SalesReturnStatus;
use App\Exceptions\SalesReturnException;
use App\Models\SalesReturn;
use Brick\Math\BigDecimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Posts a Sales Return document (spec: Order Processing → Returns). Each line is
 * run through {@see RecordSalesReturn} — stock back at moving-average cost, with
 * revenue and COGS reversals — atomically. A short/empty document aborts wholesale.
 */
class PostSalesReturn
{
    public function __construct(
        private RecordSalesReturn $recordSalesReturn,
    ) {}

    public function handle(SalesReturn $return): SalesReturn
    {
        if ($return->status === SalesReturnStatus::Posted) {
            throw SalesReturnException::alreadyPosted();
        }

        return DB::transaction(function () use ($return): SalesReturn {
            $return->loadMissing('lines.product', 'warehouse');
            $date = Carbon::parse($return->return_date)->format('Y-m-d');
            $posted = 0;

            foreach ($return->lines as $line) {
                $qty = BigDecimal::of($line->quantity);
                if ($qty->isLessThanOrEqualTo(0)) {
                    continue;
                }
                $this->recordSalesReturn->handle(
                    $return->warehouse,
                    $line->product,
                    (string) $qty,
                    (string) $line->unit_price,
                    $date,
                    $return,
                    $return->number,
                );
                $posted++;
            }

            if ($posted === 0) {
                throw SalesReturnException::empty();
            }

            $return->update(['status' => SalesReturnStatus::Posted]);

            return $return->refresh();
        });
    }
}
