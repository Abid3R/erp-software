<?php

namespace App\Filament\Pages;

use App\Enums\CommercialInvoiceStatus;
use App\Filament\Concerns\TabularReport;
use App\Filament\Concerns\WithReportDateRange;
use App\Models\CommercialInvoice;
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
 * Export Sales Register — posted commercial invoices only, i.e. actual export
 * sales booked to the ledger, valued in the base currency. Ends with a grand
 * total of export sales for the period.
 */
class ExportSalesRegister extends Page implements HasForms
{
    use HasPageShield;
    use InteractsWithForms;
    use TabularReport;
    use WithReportDateRange;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pages.tabular-report';

    protected static ?string $title = 'Export Sales Register';

    public ?int $customer = null;

    public function form(Form $form): Form
    {
        return $form->schema([
            ...$this->rangeSchema(),
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
        return 'Export Sales Register';
    }

    public function reportHeaders(): array
    {
        return ['Date', 'Invoice No.', 'Customer', 'Ccy', 'Foreign total', 'Base sale ('.config('erp.currency.code').')'];
    }

    public function reportRows(): array
    {
        $companyId = app(CompanyContext::class)->currentId();
        if ($companyId === null) {
            return [];
        }
        ['from' => $from, 'to' => $to] = $this->resolvedRange();

        $invoices = CommercialInvoice::query()->where('company_id', $companyId)
            ->where('status', CommercialInvoiceStatus::Posted->value)
            ->when($from, fn ($q) => $q->whereDate('invoice_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('invoice_date', '<=', $to))
            ->when($this->customer, fn ($q) => $q->where('customer_id', $this->customer))
            ->with(['customer', 'lines', 'taxRate'])
            ->orderBy('invoice_date')
            ->get();

        $rows = $invoices->map(fn (CommercialInvoice $ci): array => [
            $ci->invoice_date->toDateString(),
            $ci->number,
            $ci->customer?->name ?? '—',
            $ci->currency_code,
            number_format((float) (string) $ci->total(), 2),
            number_format((float) (string) $ci->toBase($ci->total()), 2),
        ])->all();

        if ($rows !== []) {
            $grand = $invoices->reduce(fn (BigDecimal $c, CommercialInvoice $ci) => $c->plus($ci->toBase($ci->total())), BigDecimal::zero());
            $rows[] = ['', '', '', '', 'Total', number_format((float) (string) $grand, 2)];
        }

        return $rows;
    }
}
