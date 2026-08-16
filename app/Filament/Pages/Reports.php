<?php

namespace App\Filament\Pages;

use App\Domain\Accounting\TrialBalance;
use App\Domain\Reporting\BalanceSheet;
use App\Domain\Reporting\ProfitAndLoss;
use App\Models\Company;
use App\Support\CompanyContext;
use Barryvdh\DomPDF\Facade\Pdf;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Brick\Math\BigDecimal;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Symfony\Component\HttpFoundation\Response;

/**
 * Financial statements for the active company and an optional date range, rendered
 * from the report services (spec #31, #32 — derived from the ledger). Supports a
 * PDF export. Presentation only; computation lives in app/Domain/Reporting.
 */
class Reports extends Page implements HasForms
{
    use HasPageShield;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationGroup = 'Accounts';

    protected static ?int $navigationSort = 7;

    protected static string $view = 'filament.pages.reports';

    public ?string $from = null;

    public ?string $to = null;

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                DatePicker::make('from')->label('From')->live(),
                DatePicker::make('to')->label('To')->live(),
            ])
            ->columns(2)
            ->statePath('');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pdf')
                ->label('Download PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(fn (): Response => $this->downloadPdf()),
        ];
    }

    /** @return array{pl: array<string, BigDecimal>|null, bs: array<string, mixed>|null, tb: array<string, BigDecimal>|null} */
    public function getReportData(): array
    {
        $companyId = app(CompanyContext::class)->currentId();

        if ($companyId === null) {
            return ['pl' => null, 'bs' => null, 'tb' => null];
        }

        return [
            'pl' => ProfitAndLoss::for($companyId, $this->from, $this->to),
            'bs' => BalanceSheet::for($companyId, $this->to),
            'tb' => TrialBalance::totals($companyId),
        ];
    }

    public function downloadPdf(): Response
    {
        $company = app(CompanyContext::class)->current();
        $report = $this->getReportData();

        abort_if($report['pl'] === null, 400, 'No active company selected.');

        $pdf = Pdf::loadView('reports.pdf', [
            'report' => $report,
            'company' => $company,
            'setting' => $company?->reportSettingOrNew(),
            'from' => $this->from,
            'to' => $this->to,
            'page' => $this,
        ]);

        $name = 'financial-report-'.($company instanceof Company ? $company->code : 'company').'.pdf';

        return response()->streamDownload(fn () => print ($pdf->output()), $name);
    }

    public function money(BigDecimal|string $value): string
    {
        $precision = (int) config('erp.currency.precision', 2);

        return config('erp.currency.symbol').number_format((float) (string) $value, $precision);
    }
}
