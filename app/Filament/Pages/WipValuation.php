<?php

namespace App\Filament\Pages;

use App\Domain\Reporting\WipValuation as WipValuationReport;
use App\Support\CompanyContext;
use App\Support\CsvExport;
use Barryvdh\DomPDF\Facade\Pdf;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Work-in-progress valuation (spec: Reports). Open manufacturing orders holding
 * capitalised WIP, from {@see WipValuationReport}; the total reconciles with the
 * GL work-in-progress account.
 */
class WipValuation extends Page
{
    use HasPageShield;

    protected static ?string $navigationIcon = 'heroicon-o-beaker';

    protected static ?string $navigationGroup = 'Manufacturing';

    protected static ?string $navigationLabel = 'WIP Valuation';

    protected static ?int $navigationSort = 6;

    protected static ?string $title = 'WIP Valuation';

    protected static string $view = 'filament.pages.wip-valuation';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pdf')->label('Download PDF')->icon('heroicon-o-arrow-down-tray')
                ->action(fn (): Response => $this->downloadPdf()),
            Action::make('csv')->label('Export CSV')->icon('heroicon-o-table-cells')->color('gray')
                ->action(fn (): StreamedResponse => $this->downloadCsv()),
        ];
    }

    /** @return array{rows: list<array<string, string>>, total: string} */
    public function getValuation(): array
    {
        $companyId = app(CompanyContext::class)->currentId();
        if ($companyId === null) {
            return ['rows' => [], 'total' => '0.00'];
        }

        return WipValuationReport::for($companyId);
    }

    public function downloadPdf(): Response
    {
        $company = app(CompanyContext::class)->current();
        abort_if($company === null, 400, 'No active company selected.');

        $pdf = Pdf::loadView('reports.wip-valuation-pdf', [
            'valuation' => $this->getValuation(),
            'company' => $company,
            'setting' => $company->reportSettingOrNew(),
        ]);

        return response()->streamDownload(fn () => print ($pdf->output()), "wip-valuation-{$company->code}.pdf");
    }

    public function downloadCsv(): StreamedResponse
    {
        $company = app(CompanyContext::class)->current();
        abort_if($company === null, 400, 'No active company selected.');

        $valuation = $this->getValuation();
        $rows = array_map(fn (array $r): array => [
            $r['type'], $r['reference'], $r['sku'], $r['product'], $r['planned'], $r['produced'], $r['status'], $r['wip'],
        ], $valuation['rows']);
        $rows[] = ['', '', '', '', '', '', 'Total', $valuation['total']];

        return CsvExport::stream(
            ['Stage', 'Ref #', 'SKU', 'Product', 'Planned', 'Produced', 'Status', 'WIP value (BDT)'],
            $rows,
            "wip-valuation-{$company->code}.csv",
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
