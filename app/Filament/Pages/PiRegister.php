<?php

namespace App\Filament\Pages;

use App\Enums\ProformaInvoiceStatus;
use App\Filament\Concerns\TabularReport;
use App\Filament\Concerns\WithReportDateRange;
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
 * PI Register — every proforma invoice in a period, with its LC allocation and
 * status. Real data from proforma_invoices.
 */
class PiRegister extends Page implements HasForms
{
    use HasPageShield;
    use InteractsWithForms;
    use TabularReport;
    use WithReportDateRange;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pages.tabular-report';

    protected static ?string $title = 'PI Register';

    public ?int $customer = null;

    public ?string $status = null;

    public function form(Form $form): Form
    {
        return $form->schema([
            ...$this->rangeSchema(),
            Select::make('customer')->label('Customer')
                ->options(fn (): array => Customer::query()->orderBy('name')->pluck('name', 'id')->all())
                ->placeholder('All customers')->live(),
            Select::make('status')->options(ProformaInvoiceStatus::options())->placeholder('All statuses')->live(),
        ])->columns(4)->statePath('');
    }

    protected function getHeaderActions(): array
    {
        return $this->tabularActions();
    }

    public function reportTitle(): string
    {
        return 'PI Register';
    }

    public function reportHeaders(): array
    {
        return ['Date', 'PI No.', 'Customer', 'LC', 'Ccy', 'Total', 'Status'];
    }

    public function reportRows(): array
    {
        $companyId = app(CompanyContext::class)->currentId();
        if ($companyId === null) {
            return [];
        }
        ['from' => $from, 'to' => $to] = $this->resolvedRange();

        return ProformaInvoice::query()->where('company_id', $companyId)
            ->when($from, fn ($q) => $q->whereDate('pi_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('pi_date', '<=', $to))
            ->when($this->customer, fn ($q) => $q->where('customer_id', $this->customer))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->with(['customer', 'letterOfCredit', 'lines', 'taxRate'])
            ->orderBy('pi_date')
            ->get()
            ->map(fn (ProformaInvoice $pi): array => [
                $pi->pi_date->toDateString(),
                $pi->number,
                $pi->customer?->name ?? '—',
                $pi->letterOfCredit?->number ?? '—',
                $pi->currency_code,
                number_format((float) (string) $pi->total(), 2),
                $pi->status->label(),
            ])->all();
    }
}
