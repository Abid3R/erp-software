<?php

namespace App\Filament\Pages;

use App\Domain\Accounting\TrialBalance;
use App\Domain\Reporting\BalanceSheet;
use App\Domain\Reporting\ProfitAndLoss;
use App\Support\CompanyContext;
use Brick\Math\BigDecimal;
use Filament\Pages\Page;

/**
 * Financial statements for the active company, rendered from the report services
 * (spec #31, #32 — derived from the ledger, never fabricated). Presentation only;
 * all computation lives in app/Domain/Reporting.
 */
class Reports extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationGroup = 'Accounting';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.reports';

    /** @return array{pl: array<string, BigDecimal>|null, bs: array<string, mixed>|null, tb: array<string, BigDecimal>|null} */
    public function getReportData(): array
    {
        $companyId = app(CompanyContext::class)->currentId();

        if ($companyId === null) {
            return ['pl' => null, 'bs' => null, 'tb' => null];
        }

        return [
            'pl' => ProfitAndLoss::for($companyId),
            'bs' => BalanceSheet::for($companyId),
            'tb' => TrialBalance::totals($companyId),
        ];
    }

    public function money(BigDecimal|string $value): string
    {
        $precision = (int) config('erp.currency.precision', 2);

        return config('erp.currency.symbol').number_format((float) (string) $value, $precision);
    }
}
