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
 * PI vs LC Report — each proforma invoice against the letter of credit it is
 * allocated to, showing the LC amount and what remains. Unallocated approved PIs
 * are flagged so nothing is left off an LC by mistake.
 */
class PiVsLcReport extends Page implements HasForms
{
    use HasPageShield;
    use InteractsWithForms;
    use TabularReport;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pages.tabular-report';

    protected static ?string $title = 'PI vs LC Report';

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
        return 'PI vs LC Report';
    }

    public function reportHeaders(): array
    {
        return ['PI No.', 'Customer', 'Ccy', 'PI total', 'LC No.', 'LC amount', 'LC remaining'];
    }

    public function reportRows(): array
    {
        $companyId = app(CompanyContext::class)->currentId();
        if ($companyId === null) {
            return [];
        }

        return ProformaInvoice::query()->where('company_id', $companyId)
            ->when($this->customer, fn ($q) => $q->where('customer_id', $this->customer))
            ->with(['customer', 'lines', 'taxRate', 'letterOfCredit.proformaInvoices.lines', 'letterOfCredit.proformaInvoices.taxRate'])
            ->orderBy('number')
            ->get()
            ->map(function (ProformaInvoice $pi): array {
                $lc = $pi->letterOfCredit;

                return [
                    $pi->number,
                    $pi->customer?->name ?? '—',
                    $pi->currency_code,
                    number_format((float) (string) $pi->total(), 2),
                    $lc?->number ?? 'NOT ALLOCATED',
                    $lc !== null ? number_format((float) $lc->amount, 2) : '—',
                    $lc !== null ? number_format((float) (string) $lc->remaining(), 2) : '—',
                ];
            })->all();
    }
}
