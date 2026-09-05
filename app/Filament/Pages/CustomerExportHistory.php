<?php

namespace App\Filament\Pages;

use App\Enums\CommercialInvoiceStatus;
use App\Filament\Concerns\TabularReport;
use App\Models\Customer;
use App\Support\CompanyContext;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Brick\Math\BigDecimal;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;

/**
 * Customer Export History — per customer, a roll-up of their export activity:
 * proforma invoices, commercial invoices, posted export sales (base currency) and
 * shipments. As-of snapshot.
 */
class CustomerExportHistory extends Page implements HasForms
{
    use HasPageShield;
    use InteractsWithForms;
    use TabularReport;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pages.tabular-report';

    protected static ?string $title = 'Customer Export History';

    public ?int $customer = null;

    public function form(Form $form): Form
    {
        return $form->schema([
            Select::make('customer')->label('Customer')
                ->options(fn (): array => Customer::query()->orderBy('name')->pluck('name', 'id')->all())
                ->placeholder('All customers')->live(),
        ])->columns(4)->statePath('');
    }

    protected function getHeaderActions(): array
    {
        return $this->tabularActions();
    }

    public function reportTitle(): string
    {
        return 'Customer Export History';
    }

    public function reportHeaders(): array
    {
        return ['Customer', 'PIs', 'Commercial invoices', 'Posted export sales ('.config('erp.currency.code').')', 'Shipments'];
    }

    public function reportRows(): array
    {
        $companyId = app(CompanyContext::class)->currentId();
        if ($companyId === null) {
            return [];
        }

        return Customer::query()->where('company_id', $companyId)
            ->when($this->customer, fn ($q) => $q->whereKey($this->customer))
            ->withCount([
                'proformaInvoices',
                'commercialInvoices',
                'exportShipments',
            ])
            ->with(['commercialInvoices' => fn ($q) => $q->where('status', CommercialInvoiceStatus::Posted->value)->with('lines', 'taxRate')])
            ->orderBy('name')
            ->get()
            ->filter(fn (Customer $c): bool => $c->proforma_invoices_count > 0 || $c->commercial_invoices_count > 0 || $c->export_shipments_count > 0)
            ->map(function (Customer $c): array {
                $sales = $c->commercialInvoices->reduce(
                    fn (BigDecimal $carry, $ci) => $carry->plus($ci->toBase($ci->total())),
                    BigDecimal::zero(),
                );

                return [
                    $c->name,
                    (string) $c->proforma_invoices_count,
                    (string) $c->commercial_invoices_count,
                    number_format((float) (string) $sales, 2),
                    (string) $c->export_shipments_count,
                ];
            })->values()->all();
    }
}
