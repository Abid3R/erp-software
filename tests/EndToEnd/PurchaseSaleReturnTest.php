<?php

use App\Actions\Purchasing\ReceiveGoods;
use App\Actions\Sales\RecordSale;
use App\Actions\Sales\RecordSalesReturn;
use App\Domain\Accounting\TrialBalance;
use App\Enums\AccountType;
use App\Enums\PeriodStatus;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\JournalLine;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Support\CompanyContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

/**
 * @return array{0: Company, 1: Warehouse, 2: Product}
 */
function commerceSetup(): array
{
    $company = Company::factory()->create(['allow_negative_stock' => false]);
    app(CompanyContext::class)->set($company);

    AccountingPeriod::create([
        'company_id' => $company->getKey(), 'name' => 'FY2026', 'fiscal_year' => 2026,
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => PeriodStatus::Open,
    ]);

    $coa = [
        ['1100', 'Accounts Receivable', AccountType::Asset],
        ['1200', 'Inventory', AccountType::Asset],
        ['2100', 'GRNI', AccountType::Liability],
        ['4000', 'Sales Revenue', AccountType::Revenue],
        ['4100', 'Sales Returns', AccountType::Revenue],
        ['5000', 'COGS', AccountType::Expense],
    ];
    foreach ($coa as [$code, $name, $type]) {
        Account::create(['company_id' => $company->getKey(), 'code' => $code, 'name' => $name, 'type' => $type]);
    }

    $warehouse = Warehouse::create(['company_id' => $company->getKey(), 'name' => 'Main', 'code' => 'WH1']);
    $unit = Unit::create(['company_id' => $company->getKey(), 'name' => 'Piece', 'code' => 'PCS', 'factor' => 1]);
    $product = Product::create([
        'company_id' => $company->getKey(), 'unit_id' => $unit->getKey(),
        'sku' => 'WIDGET', 'name' => 'Widget', 'cost_price' => 50, 'selling_price' => 100,
    ]);

    return [$company, $warehouse, $product];
}

function accountNet(int $companyId, string $code): string
{
    $account = Account::query()->where('company_id', $companyId)->where('code', $code)->firstOrFail();
    $row = JournalLine::query()
        ->where('account_id', $account->getKey())
        ->selectRaw('COALESCE(SUM(debit), 0) d, COALESCE(SUM(credit), 0) c')
        ->first();

    return (string) BigDecimal::of($row->d)->minus(BigDecimal::of($row->c))->toScale(2, RoundingMode::HALF_UP);
}

it('runs purchase -> sale -> return keeping inventory and the ledger consistent', function () {
    [$company, $warehouse, $product] = commerceSetup();
    $cid = $company->getKey();

    // --- Scenario 1: receive 70 units @ 50 ---
    app(ReceiveGoods::class)->handle($warehouse, $product, '70', '50', '2026-06-01');

    $balance = StockBalance::query()->first();
    expect((string) $balance->quantity_on_hand)->toBe('70.0000')
        ->and((string) $balance->average_cost)->toBe('50.000000')
        ->and(accountNet($cid, '1200'))->toBe('3500.00')   // Inventory (Dr)
        ->and(accountNet($cid, '2100'))->toBe('-3500.00')  // GRNI (Cr)
        ->and(TrialBalance::isBalanced($cid))->toBeTrue();

    // --- Scenario 2: sell 20 units @ 100 (cost 50) ---
    app(RecordSale::class)->handle($warehouse, $product, '20', '100', '2026-06-05');

    $balance->refresh();
    expect((string) $balance->quantity_on_hand)->toBe('50.0000')
        ->and(accountNet($cid, '1100'))->toBe('2000.00')   // Receivable (Dr)
        ->and(accountNet($cid, '4000'))->toBe('-2000.00')  // Sales (Cr)
        ->and(accountNet($cid, '5000'))->toBe('1000.00')   // COGS (Dr) 20*50
        ->and(accountNet($cid, '1200'))->toBe('2500.00')   // Inventory now 50*50
        ->and(TrialBalance::isBalanced($cid))->toBeTrue();

    // --- Scenario 3: customer returns 3 units ---
    app(RecordSalesReturn::class)->handle($warehouse, $product, '3', '100', '2026-06-10');

    $balance->refresh();
    expect((string) $balance->quantity_on_hand)->toBe('53.0000')     // 50 + 3 back
        ->and(accountNet($cid, '4100'))->toBe('300.00')    // Sales Returns (Dr) 3*100
        ->and(accountNet($cid, '1100'))->toBe('1700.00')   // Receivable 2000 - 300
        ->and(accountNet($cid, '5000'))->toBe('850.00')    // COGS 1000 - 150 (3*50)
        ->and(accountNet($cid, '1200'))->toBe('2650.00')   // Inventory 53*50
        ->and(TrialBalance::isBalanced($cid))->toBeTrue();
});
