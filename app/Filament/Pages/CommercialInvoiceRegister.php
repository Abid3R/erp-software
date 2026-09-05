<?php

namespace App\Filament\Pages;

use App\Enums\CommercialInvoiceStatus;
use App\Filament\Concerns\TabularReport;
use App\Filament\Concerns\WithReportDateRange;
use App\Models\CommercialInvoice;
use App\Models\Customer;
use App\Support\CompanyContext;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;

/**
 * Commercial Invoice Register — every export commercial invoice in a period,
 * whatever its status, with foreign and base-currency totals.
 */
class CommercialInvoiceRegister extends Page implements HasForms
{
    use HasPageShield;
    use InteractsWithForms;
    use TabularReport;
    use WithReportDateRange;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pages.tabular-report';

    protected static ?string $title = 'Commercial Invoice Register';

    public ?int $customer = null;

    public ?string $status = null;

    public function form(Form $form): Form
    {
        return $form->schema([
            ...$this->rangeSchema(),
            Select::make('customer')->label('Customer')
                ->options(fn (): array => Customer::query()->orderBy('name')->pluck('name', 'id')->all())
                ->placeholder('All customers')->live(),
            Select::make('status')->options(CommercialInvoiceStatus::options())->placeholder('All statuses')->live(),
        ])->columns(4)->statePath('');
    }

    protected function getHeaderActions(): array
    {
        return $this->tabularActions();
    }

    public function reportTitle(): string
    {
        return 'Commercial Invoice Register';
    }

    public function reportHeaders(): array
    {
        return ['Date', 'Invoice No.', 'Customer', 'LC', 'Ccy', 'Foreign total', 'Base ('.config('erp.currency.code').')', 'Status'];
    }

    public function reportRows(): array
    {
        $companyId = app(CompanyContext::class)->currentId();
        if ($companyId === null) {
            return [];
        }
        ['from' => $from, 'to' => $to] = $this->resolvedRange();

        return CommercialInvoice::query()->where('company_id', $companyId)
            ->when($from, fn ($q) => $q->whereDate('invoice_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('invoice_date', '<=', $to))
            ->when($this->customer, fn ($q) => $q->where('customer_id', $this->customer))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->with(['customer', 'letterOfCredit', 'lines', 'taxRate'])
            ->orderBy('invoice_date')
            ->get()
            ->map(fn (CommercialInvoice $ci): array => [
                $ci->invoice_date->toDateString(),
                $ci->number,
                $ci->customer?->name ?? '—',
                $ci->letterOfCredit?->number ?? '—',
                $ci->currency_code,
                number_format((float) (string) $ci->total(), 2),
                number_format((float) (string) $ci->toBase($ci->total()), 2),
                $ci->status->label(),
            ])->all();
    }
}
