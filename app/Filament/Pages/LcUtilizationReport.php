<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\TabularReport;
use App\Models\Customer;
use App\Models\LetterOfCredit;
use App\Support\CompanyContext;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * LC Utilization Report — for each letter of credit, how much has been allocated
 * to proforma invoices and how much remains, with the utilisation percentage.
 * As-of snapshot from live data.
 */
class LcUtilizationReport extends Page implements HasForms
{
    use HasPageShield;
    use InteractsWithForms;
    use TabularReport;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pages.tabular-report';

    protected static ?string $title = 'LC Utilization Report';

    public ?int $customer = null;

    public function form(Form $form): Form
    {
        return $form->schema([
            Select::make('customer')->label('Applicant')
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
        return 'LC Utilization Report';
    }

    public function reportHeaders(): array
    {
        return ['LC No.', 'Applicant', 'Ccy', 'Amount', 'Allocated', 'Remaining', 'Utilised %', 'PIs', 'Status'];
    }

    public function reportRows(): array
    {
        $companyId = app(CompanyContext::class)->currentId();
        if ($companyId === null) {
            return [];
        }

        return LetterOfCredit::query()->where('company_id', $companyId)
            ->when($this->customer, fn ($q) => $q->where('customer_id', $this->customer))
            ->with(['customer', 'proformaInvoices.lines', 'proformaInvoices.taxRate'])
            ->orderBy('number')
            ->get()
            ->map(function (LetterOfCredit $lc): array {
                $amount = BigDecimal::of($lc->amount);
                $allocated = $lc->allocated();
                $pct = $amount->isZero() ? '0.0'
                    : (string) $allocated->multipliedBy(100)->dividedBy($amount, 1, RoundingMode::HALF_UP);

                return [
                    $lc->number,
                    $lc->customer?->name ?? '—',
                    $lc->currency_code,
                    number_format((float) $lc->amount, 2),
                    number_format((float) (string) $allocated, 2),
                    number_format((float) (string) $lc->remaining(), 2),
                    $pct.'%',
                    (string) $lc->proformaInvoices->reject(fn ($pi) => $pi->status->isCancelled())->count(),
                    $lc->status->label(),
                ];
            })->all();
    }
}
