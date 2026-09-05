<?php

namespace App\Filament\Pages;

use App\Enums\ExportShipmentStatus;
use App\Filament\Concerns\TabularReport;
use App\Filament\Concerns\WithReportDateRange;
use App\Models\Customer;
use App\Models\ExportShipment;
use App\Support\CompanyContext;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;

/**
 * Shipment Register — export consignments in a period, with vessel, container,
 * BL/AWB and status. Real data from export_shipments.
 */
class ShipmentRegister extends Page implements HasForms
{
    use HasPageShield;
    use InteractsWithForms;
    use TabularReport;
    use WithReportDateRange;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pages.tabular-report';

    protected static ?string $title = 'Shipment Register';

    public ?int $customer = null;

    public ?string $status = null;

    public function form(Form $form): Form
    {
        return $form->schema([
            ...$this->rangeSchema(),
            Select::make('customer')->label('Customer')
                ->options(fn (): array => Customer::query()->orderBy('name')->pluck('name', 'id')->all())
                ->placeholder('All customers')->live(),
            Select::make('status')->options(ExportShipmentStatus::options())->placeholder('All statuses')->live(),
        ])->columns(4)->statePath('');
    }

    protected function getHeaderActions(): array
    {
        return $this->tabularActions();
    }

    public function reportTitle(): string
    {
        return 'Shipment Register';
    }

    public function reportHeaders(): array
    {
        return ['Date', 'Shipment No.', 'Customer', 'Invoice', 'BL/AWB', 'Container', 'Port of discharge', 'Status'];
    }

    public function reportRows(): array
    {
        $companyId = app(CompanyContext::class)->currentId();
        if ($companyId === null) {
            return [];
        }
        ['from' => $from, 'to' => $to] = $this->resolvedRange();

        return ExportShipment::query()->where('company_id', $companyId)
            ->when($from, fn ($q) => $q->whereDate('shipment_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('shipment_date', '<=', $to))
            ->when($this->customer, fn ($q) => $q->where('customer_id', $this->customer))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->with(['customer', 'commercialInvoice'])
            ->orderBy('shipment_date')
            ->get()
            ->map(fn (ExportShipment $s): array => [
                $s->shipment_date?->toDateString() ?? '—',
                $s->number,
                $s->customer?->name ?? '—',
                $s->commercialInvoice?->number ?? '—',
                $s->bl_awb_no ?? '—',
                $s->container_no ?? '—',
                $s->port_of_discharge ?? '—',
                $s->status->label(),
            ])->all();
    }
}
