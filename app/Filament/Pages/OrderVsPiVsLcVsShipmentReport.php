<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\TabularReport;
use App\Models\Customer;
use App\Models\ProformaInvoice;
use App\Support\CompanyContext;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;

/**
 * Order vs PI vs LC vs Shipment Report — the whole export chain on one line per
 * proforma invoice: the sales order it came from, the LC it sits on, the
 * commercial invoice raised, and the shipment it went out on. Shows how far each
 * export deal has progressed.
 */
class OrderVsPiVsLcVsShipmentReport extends Page implements HasForms
{
    use HasPageShield;
    use InteractsWithForms;
    use TabularReport;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pages.tabular-report';

    protected static ?string $title = 'Order → PI → LC → Shipment';

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
        return 'Order vs PI vs LC vs Shipment';
    }

    public function reportHeaders(): array
    {
        return ['Customer', 'Sales order', 'PI', 'LC', 'Commercial invoice', 'Shipment', 'Shipment status'];
    }

    public function reportRows(): array
    {
        $companyId = app(CompanyContext::class)->currentId();
        if ($companyId === null) {
            return [];
        }

        return ProformaInvoice::query()->where('company_id', $companyId)
            ->when($this->customer, fn ($q) => $q->where('customer_id', $this->customer))
            ->with(['customer', 'salesOrder', 'letterOfCredit', 'commercialInvoices.shipment', 'shipments'])
            ->orderBy('number')
            ->get()
            ->map(function (ProformaInvoice $pi): array {
                $ci = $pi->commercialInvoices->first();
                $shipment = $ci?->shipment ?? $pi->shipments->first();

                return [
                    $pi->customer?->name ?? '—',
                    $pi->salesOrder?->so_number ?? '—',
                    $pi->number,
                    $pi->letterOfCredit?->number ?? '—',
                    $ci?->number ?? '—',
                    $shipment?->number ?? '—',
                    $shipment?->status->label() ?? 'Not shipped',
                ];
            })->all();
    }
}
