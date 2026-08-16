<?php

namespace App\Filament\Pages;

use App\Domain\Reporting\StockValuation as StockValuationReport;
use App\Models\Warehouse;
use App\Support\CompanyContext;
use Barryvdh\DomPDF\Facade\Pdf;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stock valuation report (spec: Reports — Phase 15). On-hand value per product/
 * warehouse from {@see StockValuationReport}; the total reconciles with the GL
 * inventory control account.
 */
class StockValuation extends Page implements HasForms
{
    use HasPageShield;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?string $navigationLabel = 'Stock Valuation';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.stock-valuation';

    public ?int $warehouseId = null;

    public function form(Form $form): Form
    {
        return $form->schema([
            Select::make('warehouseId')->label('Warehouse')
                ->options(fn (): array => Warehouse::query()->orderBy('name')->pluck('name', 'id')->all())
                ->placeholder('All warehouses')->live(),
        ])->statePath('');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pdf')->label('Download PDF')->icon('heroicon-o-arrow-down-tray')
                ->action(fn (): Response => $this->downloadPdf()),
        ];
    }

    /** @return array{rows: list<array<string, string>>, total: string} */
    public function getValuation(): array
    {
        $companyId = app(CompanyContext::class)->currentId();
        if ($companyId === null) {
            return ['rows' => [], 'total' => '0.00'];
        }

        return StockValuationReport::for($companyId, $this->warehouseId);
    }

    public function downloadPdf(): Response
    {
        $company = app(CompanyContext::class)->current();
        abort_if($company === null, 400, 'No active company selected.');

        $pdf = Pdf::loadView('reports.stock-valuation-pdf', [
            'valuation' => $this->getValuation(),
            'company' => $company,
            'setting' => $company->reportSettingOrNew(),
        ]);

        return response()->streamDownload(fn () => print ($pdf->output()), "stock-valuation-{$company->code}.pdf");
    }

    public function money(string $value): string
    {
        return config('erp.currency.symbol').number_format((float) $value, (int) config('erp.currency.precision', 2));
    }

    public function qty(string $value): string
    {
        return rtrim(rtrim($value, '0'), '.');
    }
}
