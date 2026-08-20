<?php

namespace App\Services\Gemini;

use App\Domain\Reporting\DashboardMetrics;
use App\Domain\Reporting\PartyAging;
use App\Domain\Reporting\ProfitAndLoss;
use App\Domain\Reporting\PurchaseRegister;
use App\Domain\Reporting\SalesRegister;
use App\Domain\Reporting\StockValuation;
use App\Domain\Reporting\VoucherRegister;
use App\Models\Company;
use App\Models\Product;
use App\Models\StockBalance;
use Brick\Math\BigDecimal;
use Illuminate\Support\Carbon;

/**
 * Builds a compact, read-only snapshot of a company's key figures for the AI
 * assistant to ground its answers on. Every number comes from the SAME existing
 * report services the rest of the ERP uses, so answers reconcile with the
 * on-screen reports. Only aggregates and top-5 lists are included — never full
 * data tables — to bound what leaves the system.
 */
class ErpDataSnapshot
{
    private const TOP_N = 5;

    public function for(Company $company): string
    {
        $cid = (int) $company->getKey();
        $symbol = (string) config('erp.currency.symbol', '৳');
        $now = Carbon::now();
        $monthStart = $now->copy()->startOfMonth()->toDateString();
        $monthEnd = $now->copy()->endOfMonth()->toDateString();
        $yearStart = $now->copy()->startOfYear()->toDateString();

        $metrics = app(DashboardMetrics::class)->for($cid);
        $plMonth = ProfitAndLoss::for($cid, $monthStart, $monthEnd);
        $plYtd = ProfitAndLoss::for($cid, $yearStart, $now->toDateString());
        $ar = PartyAging::receivables($cid);
        $ap = PartyAging::payables($cid);
        $stock = StockValuation::for($cid);
        $salesMonth = SalesRegister::for($cid, $monthStart, $monthEnd);
        $purchaseMonth = PurchaseRegister::for($cid, $monthStart, $monthEnd);
        $receiptsMonth = VoucherRegister::receipts($cid, $monthStart, $monthEnd);
        $paymentsMonth = VoucherRegister::payments($cid, $monthStart, $monthEnd);

        $m = fn (BigDecimal|string $v): string => $symbol.number_format((float) (string) $v, (int) config('erp.currency.precision', 2));

        $lines = [];
        $lines[] = "Company: {$company->name} (currency {$symbol}). Snapshot date: {$now->toDateString()}.";
        $lines[] = '';
        $lines[] = 'CURRENT BALANCES:';
        $lines[] = '- Inventory value: '.$m($metrics['inventory_value']);
        $lines[] = '- Receivables (AR) outstanding: '.$m($metrics['receivables']);
        $lines[] = '- Payables (AP) outstanding: '.$m($metrics['payables']);
        $lines[] = '';
        $lines[] = 'PROFIT & LOSS:';
        $lines[] = "- This month ({$monthStart}..{$monthEnd}): revenue ".$m($plMonth['revenue']).', expenses '.$m($plMonth['expenses']).', net profit '.$m($plMonth['net_profit']);
        $lines[] = "- Year to date (since {$yearStart}): revenue ".$m($plYtd['revenue']).', expenses '.$m($plYtd['expenses']).', net profit '.$m($plYtd['net_profit']);
        $lines[] = '';
        $lines[] = 'THIS MONTH ACTIVITY:';
        $lines[] = '- Sales orders: ordered '.$m($salesMonth['ordered']).', delivered '.$m($salesMonth['delivered']);
        $lines[] = '- Supplier invoices (purchases) net: '.$m($purchaseMonth['net']);
        $lines[] = '- Customer receipts: '.$m($receiptsMonth['total'])." ({$receiptsMonth['count']} vouchers)";
        $lines[] = '- Supplier payments: '.$m($paymentsMonth['total'])." ({$paymentsMonth['count']} vouchers)";
        $lines[] = '';
        $lines[] = 'RECEIVABLES AGING (totals): current '.$m($ar['totals']['current']).', 31-60 '.$m($ar['totals']['d30']).', 61-90 '.$m($ar['totals']['d60']).', 90+ '.$m($ar['totals']['d90plus']).', total '.$m($ar['totals']['total']);
        $lines[] = 'Top customers owing:';
        $lines = array_merge($lines, $this->topParties($ar['rows'], $m));
        $lines[] = '';
        $lines[] = 'PAYABLES AGING (totals): current '.$m($ap['totals']['current']).', 31-60 '.$m($ap['totals']['d30']).', 61-90 '.$m($ap['totals']['d60']).', 90+ '.$m($ap['totals']['d90plus']).', total '.$m($ap['totals']['total']);
        $lines[] = 'Top suppliers owed:';
        $lines = array_merge($lines, $this->topParties($ap['rows'], $m));
        $lines[] = '';
        $lines[] = 'INVENTORY — total valuation '.$m($stock['total']).'. Top items by value:';
        $lines = array_merge($lines, $this->topStock($stock['rows'], $m));
        $lines[] = '';
        $lines[] = 'LOW STOCK (at or below reorder level):';
        $lines = array_merge($lines, $this->lowStock($cid, $m));

        return implode("\n", $lines);
    }

    /**
     * @param  list<array{name: string, total: string}>  $rows
     * @param  callable(string): string  $m
     * @return list<string>
     */
    private function topParties(array $rows, callable $m): array
    {
        usort($rows, fn (array $a, array $b): int => (float) $b['total'] <=> (float) $a['total']);
        $rows = array_slice($rows, 0, self::TOP_N);

        if ($rows === []) {
            return ['  (none)'];
        }

        $share = (bool) config('services.gemini.share_party_names', true);
        $out = [];
        foreach ($rows as $i => $row) {
            $name = $share ? $row['name'] : ('Party #'.($i + 1));
            $out[] = "  - {$name}: ".$m($row['total']);
        }

        return $out;
    }

    /**
     * @param  list<array{sku: string, product: string, value: string}>  $rows
     * @param  callable(string): string  $m
     * @return list<string>
     */
    private function topStock(array $rows, callable $m): array
    {
        $byProduct = [];
        foreach ($rows as $row) {
            $key = $row['sku'].' '.$row['product'];
            $byProduct[$key] = ($byProduct[$key] ?? 0) + (float) $row['value'];
        }
        arsort($byProduct);
        $byProduct = array_slice($byProduct, 0, self::TOP_N, true);

        if ($byProduct === []) {
            return ['  (no stock on hand)'];
        }

        $out = [];
        foreach ($byProduct as $label => $value) {
            $out[] = "  - {$label}: ".$m((string) $value);
        }

        return $out;
    }

    /**
     * @param  callable(string): string  $m
     * @return list<string>
     */
    private function lowStock(int $companyId, callable $m): array
    {
        $reorder = Product::query()->where('company_id', $companyId)
            ->whereNotNull('reorder_level')->where('reorder_level', '>', 0)
            ->pluck('reorder_level', 'id');

        if ($reorder->isEmpty()) {
            return ['  (no reorder levels configured)'];
        }

        $onHand = StockBalance::query()->where('company_id', $companyId)
            ->whereIn('product_id', $reorder->keys())
            ->selectRaw('product_id, COALESCE(SUM(quantity_on_hand), 0) as qty')
            ->groupBy('product_id')->pluck('qty', 'product_id');

        $short = [];
        foreach ($reorder as $productId => $level) {
            $qty = (float) ($onHand[$productId] ?? 0);
            if ($qty <= (float) $level) {
                $short[$productId] = ['qty' => $qty, 'level' => (float) $level];
            }
        }

        if ($short === []) {
            return ['  (stock healthy — nothing at/below reorder level)'];
        }

        $names = Product::query()->whereIn('id', array_keys($short))->pluck('name', 'id');
        $out = [];
        foreach (array_slice($short, 0, self::TOP_N, true) as $productId => $info) {
            $name = (string) ($names[$productId] ?? ('#'.$productId));
            $out[] = "  - {$name}: on hand {$info['qty']}, reorder at {$info['level']}";
        }

        return $out;
    }
}
