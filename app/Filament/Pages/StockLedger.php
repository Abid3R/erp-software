<?php

namespace App\Filament\Pages;

use App\Domain\Reporting\StockLedger as StockLedgerReport;
use App\Models\Product;
use App\Models\Warehouse;
use App\Support\CompanyContext;
use Barryvdh\DomPDF\Facade\Pdf;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stock ledger report (spec: Reports — Phase 15). Every inventory movement for a
 * chosen product in date order from {@see StockLedgerReport} — the inventory
 * equivalent of the general ledger.
 */
class StockLedger extends Page implements HasForms
{
    use HasPageShield;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?string $navigationLabel = 'Stock Ledger';

    protected static ?int $navigationSort = 6;

    protected static string $view = 'filament.pages.stock-ledger';

    public ?int $productId = null;

    public ?int $warehouseId = null;

    public ?string $from = null;

    public ?string $to = null;

    public function form(Form $form): Form
    {
        return $form->schema([
            Select::make('productId')->label('Product')
                ->options(fn (): array => Product::query()->orderBy('name')->pluck('name', 'id')->all())
                ->searchable()->live(),
            Select::make('warehouseId')->label('Warehouse')
                ->options(fn (): array => Warehouse::query()->orderBy('name')->pluck('name', 'id')->all())
                ->placeholder('All warehouses')->live(),
            DatePicker::make('from')->live(),
            DatePicker::make('to')->live(),
        ])->columns(4)->statePath('');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pdf')->label('Download PDF')->icon('heroicon-o-arrow-down-tray')
                ->visible(fn (): bool => $this->productId !== null)
                ->action(fn (): Response => $this->downloadPdf()),
        ];
    }

    /** @return array{rows: list<array<string, string>>, in: string, out: string}|null */
    public function getLedger(): ?array
    {
        if ($this->productId === null) {
            return null;
        }
        $product = Product::find($this->productId);
        if ($product === null) {
            return null;
        }

        return StockLedgerReport::forProduct($product, $this->warehouseId, $this->from, $this->to);
    }

    public function downloadPdf(): Response
    {
        $company = app(CompanyContext::class)->current();
        $product = $this->productId !== null ? Product::find($this->productId) : null;
        abort_if($company === null || $product === null, 400, 'Select a product first.');

        $pdf = Pdf::loadView('reports.stock-ledger-pdf', [
            'ledger' => $this->getLedger(),
            'product' => $product,
            'company' => $company,
            'setting' => $company->reportSettingOrNew(),
            'from' => $this->from,
            'to' => $this->to,
        ]);

        return response()->streamDownload(fn () => print ($pdf->output()), "stock-ledger-{$product->sku}.pdf");
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
