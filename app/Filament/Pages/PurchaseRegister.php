<?php

namespace App\Filament\Pages;

use App\Domain\Reporting\PurchaseRegister as PurchaseRegisterReport;
use App\Filament\Concerns\WithReportDateRange;
use App\Support\CompanyContext;
use App\Support\CsvExport;
use Barryvdh\DomPDF\Facade\Pdf;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Purchase register (spec: Reports — Phase 15). Supplier invoices in a period with
 * their net value, from {@see PurchaseRegisterReport}.
 */
class PurchaseRegister extends Page implements HasForms
{
    use HasPageShield;
    use InteractsWithForms;
    use WithReportDateRange;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Purchasing';

    protected static ?string $navigationLabel = 'Purchase Register';

    protected static ?int $navigationSort = 9;

    protected static string $view = 'filament.pages.purchase-register';

    public function form(Form $form): Form
    {
        return $form->schema($this->rangeSchema())->columns(3)->statePath('');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pdf')->label('Download PDF')->icon('heroicon-o-arrow-down-tray')
                ->action(fn (): Response => $this->downloadPdf()),
            Action::make('csv')->label('Export CSV')->icon('heroicon-o-table-cells')->color('gray')
                ->action(fn (): StreamedResponse => $this->downloadCsv()),
        ];
    }

    /** @return array{rows: list<array<string, string>>, net: string} */
    public function getRegister(): array
    {
        $companyId = app(CompanyContext::class)->currentId();
        if ($companyId === null) {
            return ['rows' => [], 'net' => '0.00'];
        }

        ['from' => $from, 'to' => $to] = $this->resolvedRange();

        return PurchaseRegisterReport::for($companyId, $from, $to);
    }

    public function downloadPdf(): Response
    {
        $company = app(CompanyContext::class)->current();
        abort_if($company === null, 400, 'No active company selected.');

        ['from' => $from, 'to' => $to] = $this->resolvedRange();

        $pdf = Pdf::loadView('reports.purchase-register-pdf', [
            'register' => $this->getRegister(),
            'company' => $company,
            'setting' => $company->reportSettingOrNew(),
            'from' => $from,
            'to' => $to,
        ]);

        return response()->streamDownload(fn () => print ($pdf->output()), "purchase-register-{$company->code}.pdf");
    }

    public function downloadCsv(): StreamedResponse
    {
        $company = app(CompanyContext::class)->current();
        abort_if($company === null, 400, 'No active company selected.');

        $register = $this->getRegister();
        $rows = array_map(fn (array $r): array => [
            $r['date'], $r['number'], $r['supplier'], $r['reference'], $r['status'], $r['net'],
        ], $register['rows']);
        $rows[] = ['', '', '', '', 'Total', $register['net']];

        return CsvExport::stream(
            ['Date', 'Invoice #', 'Supplier', 'Reference', 'Status', 'Net (BDT)'],
            $rows,
            "purchase-register-{$company->code}.csv",
        );
    }

    public function money(string $value): string
    {
        return config('erp.currency.symbol').number_format((float) $value, (int) config('erp.currency.precision', 2));
    }
}
