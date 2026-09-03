<?php

namespace App\Filament\Pages;

use App\Domain\Manufacturing\MrpPlanner;
use App\Support\CompanyContext;
use App\Support\CsvExport;
use Barryvdh\DomPDF\Facade\Pdf;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Material Requirements Planning view. Shows what to purchase or manufacture to
 * cover open sales-order demand (computation lives in MrpPlanner).
 */
class Mrp extends Page
{
    use HasPageShield;

    protected static ?string $navigationIcon = 'heroicon-o-square-3-stack-3d';

    protected static ?string $navigationGroup = 'Manufacturing';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'MRP';

    protected static ?string $navigationLabel = 'MRP';

    protected static string $view = 'filament.pages.mrp';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pdf')->label('Download PDF')->icon('heroicon-o-arrow-down-tray')
                ->action(fn (): Response => $this->downloadPdf()),
            Action::make('csv')->label('Export CSV')->icon('heroicon-o-table-cells')->color('gray')
                ->action(fn (): StreamedResponse => $this->downloadCsv()),
        ];
    }

    /** @return list<array{product_id: int, product: string, demand: string, on_hand: string, incoming: string, net: string, action: string}> */
    public function getSuggestions(): array
    {
        $companyId = app(CompanyContext::class)->currentId();

        return $companyId === null ? [] : MrpPlanner::plan($companyId);
    }

    public function downloadPdf(): Response
    {
        $company = app(CompanyContext::class)->current();
        abort_if($company === null, 400, 'No active company selected.');

        $pdf = Pdf::loadView('reports.mrp-pdf', [
            'suggestions' => $this->getSuggestions(),
            'company' => $company,
            'setting' => $company->reportSettingOrNew(),
        ])->setPaper('a4', 'landscape');

        return response()->streamDownload(fn () => print ($pdf->output()), "mrp-{$company->code}.pdf");
    }

    public function downloadCsv(): StreamedResponse
    {
        $company = app(CompanyContext::class)->current();
        abort_if($company === null, 400, 'No active company selected.');

        $rows = array_map(fn (array $s): array => [
            $s['sku'], $s['product'], $s['demand'], $s['on_hand'], $s['reserved'],
            $s['incoming'], $s['net'], ucfirst($s['action']), $s['reason'],
        ], $this->getSuggestions());

        return CsvExport::stream(
            ['SKU', 'Product', 'Demand', 'On hand', 'Reserved', 'Incoming', 'Net required', 'Action', 'Reason'],
            $rows,
            "mrp-{$company->code}.csv",
        );
    }

    /** Trim a decimal string for display (drop trailing zeros). */
    public function qty(string $value): string
    {
        return rtrim(rtrim($value, '0'), '.') ?: '0';
    }
}
