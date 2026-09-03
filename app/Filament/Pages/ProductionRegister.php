<?php

namespace App\Filament\Pages;

use App\Domain\Reporting\ProductionRegister as ProductionRegisterReport;
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
 * Production register (spec: Reports). Finished goods produced in a period from
 * {@see ProductionRegisterReport}, reconciling with the WIP → finished-goods
 * postings.
 */
class ProductionRegister extends Page implements HasForms
{
    use HasPageShield;
    use InteractsWithForms;
    use WithReportDateRange;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Manufacturing';

    protected static ?string $navigationLabel = 'Production Register';

    protected static ?int $navigationSort = 5;

    protected static string $view = 'filament.pages.production-register';

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

    /** @return array{rows: list<array<string, string>>, total_qty: string, total_value: string} */
    public function getRegister(): array
    {
        $companyId = app(CompanyContext::class)->currentId();
        if ($companyId === null) {
            return ['rows' => [], 'total_qty' => '0', 'total_value' => '0.00'];
        }

        ['from' => $from, 'to' => $to] = $this->resolvedRange();

        return ProductionRegisterReport::for($companyId, $from, $to);
    }

    public function downloadPdf(): Response
    {
        $company = app(CompanyContext::class)->current();
        abort_if($company === null, 400, 'No active company selected.');

        ['from' => $from, 'to' => $to] = $this->resolvedRange();

        $pdf = Pdf::loadView('reports.production-register-pdf', [
            'register' => $this->getRegister(),
            'company' => $company,
            'setting' => $company->reportSettingOrNew(),
            'from' => $from,
            'to' => $to,
        ]);

        return response()->streamDownload(fn () => print ($pdf->output()), "production-register-{$company->code}.pdf");
    }

    public function downloadCsv(): StreamedResponse
    {
        $company = app(CompanyContext::class)->current();
        abort_if($company === null, 400, 'No active company selected.');

        $register = $this->getRegister();
        $rows = array_map(fn (array $r): array => [
            $r['date'], $r['reference'], $r['sku'], $r['product'], $r['quantity'], $r['unit_cost'], $r['value'],
        ], $register['rows']);
        $rows[] = ['', '', '', 'Total', $register['total_qty'], '', $register['total_value']];

        return CsvExport::stream(
            ['Date', 'MO #', 'SKU', 'Product', 'Qty', 'Unit cost', 'Value (BDT)'],
            $rows,
            "production-register-{$company->code}.csv",
        );
    }

    public function money(string $value): string
    {
        return config('erp.currency.symbol').number_format((float) $value, (int) config('erp.currency.precision', 2));
    }

    public function qty(string $value): string
    {
        return rtrim(rtrim($value, '0'), '.') ?: '0';
    }
}
