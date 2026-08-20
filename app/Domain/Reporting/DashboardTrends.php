<?php

namespace App\Domain\Reporting;

use App\Enums\AccountType;
use App\Enums\JournalStatus;
use App\Models\JournalLine;
use App\Models\SalesOrderLine;
use App\Models\SupplierInvoiceLine;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;

/**
 * Monthly time-series for the dashboard charts. All series are derived from the
 * same authoritative sources the existing reports use — the posted ledger for
 * revenue/expenses (matches P&L), sales-order lines for the sales trend (matches
 * the Sales Register), and supplier-invoice lines for the purchase trend
 * (matches the Purchase Register). No parallel calculations, no fabricated data.
 */
final class DashboardTrends
{
    private const SCALE = 2;

    /**
     * @return array{
     *   labels: list<string>,
     *   revenue: list<string>,
     *   expenses: list<string>,
     *   sales: list<string>,
     *   purchases: list<string>
     * }
     */
    public static function monthly(int $companyId, string $from, string $to): array
    {
        $start = Carbon::parse($from)->startOfMonth();
        $end = Carbon::parse($to)->endOfMonth();

        $months = self::monthRange($start, $end);
        $labels = array_map(fn (Carbon $m): string => $m->format('M Y'), $months);

        return [
            'labels' => $labels,
            'revenue' => self::ledgerSeries($companyId, AccountType::Revenue, $months),
            'expenses' => self::ledgerSeries($companyId, AccountType::Expense, $months),
            'sales' => self::salesSeries($companyId, $months),
            'purchases' => self::purchaseSeries($companyId, $months),
        ];
    }

    /**
     * @param  list<Carbon>  $months
     * @return list<string>
     */
    private static function ledgerSeries(int $companyId, AccountType $type, array $months): array
    {
        if ($months === []) {
            return [];
        }

        $first = $months[0]->copy()->startOfMonth();
        $last = end($months)->copy()->endOfMonth();

        $rows = JournalLine::withoutGlobalScopes()
            ->join('journals', 'journals.id', '=', 'journal_lines.journal_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('journals.status', JournalStatus::Posted->value)
            ->where('journal_lines.company_id', $companyId)
            ->where('accounts.type', $type->value)
            ->whereDate('journals.date', '>=', $first->toDateString())
            ->whereDate('journals.date', '<=', $last->toDateString())
            ->toBase()
            ->selectRaw("to_char(journals.date, 'YYYY-MM') as ym, "
                .'COALESCE(SUM(journal_lines.debit), 0) d, '
                .'COALESCE(SUM(journal_lines.credit), 0) c')
            ->groupBy('ym')
            ->get()
            ->keyBy('ym');

        $out = [];
        foreach ($months as $month) {
            $key = $month->format('Y-m');
            $row = $rows->get($key);
            $debit = BigDecimal::of($row->d ?? '0');
            $credit = BigDecimal::of($row->c ?? '0');
            $net = $type->isDebitNormal() ? $debit->minus($credit) : $credit->minus($debit);
            $out[] = (string) $net->toScale(self::SCALE, RoundingMode::HALF_UP);
        }

        return $out;
    }

    /**
     * @param  list<Carbon>  $months
     * @return list<string>
     */
    private static function salesSeries(int $companyId, array $months): array
    {
        if ($months === []) {
            return [];
        }

        $first = $months[0]->copy()->startOfMonth();
        $last = end($months)->copy()->endOfMonth();

        $rows = SalesOrderLine::query()
            ->join('sales_orders', 'sales_orders.id', '=', 'sales_order_lines.sales_order_id')
            ->where('sales_orders.company_id', $companyId)
            ->whereDate('sales_orders.order_date', '>=', $first->toDateString())
            ->whereDate('sales_orders.order_date', '<=', $last->toDateString())
            ->toBase()
            ->selectRaw("to_char(sales_orders.order_date, 'YYYY-MM') as ym, "
                .'COALESCE(SUM(sales_order_lines.quantity_ordered * sales_order_lines.unit_price), 0) v')
            ->groupBy('ym')
            ->get()
            ->keyBy('ym');

        return array_map(function (Carbon $month) use ($rows): string {
            $v = BigDecimal::of(($rows->get($month->format('Y-m'))->v ?? '0'));

            return (string) $v->toScale(self::SCALE, RoundingMode::HALF_UP);
        }, $months);
    }

    /**
     * @param  list<Carbon>  $months
     * @return list<string>
     */
    private static function purchaseSeries(int $companyId, array $months): array
    {
        if ($months === []) {
            return [];
        }

        $first = $months[0]->copy()->startOfMonth();
        $last = end($months)->copy()->endOfMonth();

        $rows = SupplierInvoiceLine::query()
            ->join('supplier_invoices', 'supplier_invoices.id', '=', 'supplier_invoice_lines.supplier_invoice_id')
            ->where('supplier_invoices.company_id', $companyId)
            ->whereDate('supplier_invoices.invoice_date', '>=', $first->toDateString())
            ->whereDate('supplier_invoices.invoice_date', '<=', $last->toDateString())
            ->toBase()
            ->selectRaw("to_char(supplier_invoices.invoice_date, 'YYYY-MM') as ym, "
                .'COALESCE(SUM(supplier_invoice_lines.amount), 0) v')
            ->groupBy('ym')
            ->get()
            ->keyBy('ym');

        return array_map(function (Carbon $month) use ($rows): string {
            $v = BigDecimal::of(($rows->get($month->format('Y-m'))->v ?? '0'));

            return (string) $v->toScale(self::SCALE, RoundingMode::HALF_UP);
        }, $months);
    }

    /**
     * Inclusive month buckets between two dates.
     *
     * @return list<Carbon>
     */
    private static function monthRange(Carbon $start, Carbon $end): array
    {
        $months = [];
        $cursor = $start->copy()->startOfMonth();
        $stop = $end->copy()->startOfMonth();

        while ($cursor->lessThanOrEqualTo($stop)) {
            $months[] = $cursor->copy();
            $cursor->addMonth();
        }

        return $months;
    }
}
