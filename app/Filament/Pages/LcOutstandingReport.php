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

/**
 * LC Outstanding / Remaining Report — active letters of credit that still have
 * value available for allocation, with expiry so nothing lapses unused.
 */
class LcOutstandingReport extends Page implements HasForms
{
    use HasPageShield;
    use InteractsWithForms;
    use TabularReport;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pages.tabular-report';

    protected static ?string $title = 'LC Outstanding Report';

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
        return 'LC Outstanding Report';
    }

    public function reportHeaders(): array
    {
        return ['LC No.', 'Applicant', 'Ccy', 'Amount', 'Allocated', 'Remaining', 'Expiry', 'Status'];
    }

    public function reportRows(): array
    {
        $companyId = app(CompanyContext::class)->currentId();
        if ($companyId === null) {
            return [];
        }

        return LetterOfCredit::query()->where('company_id', $companyId)
            ->whereNotIn('status', ['cancelled', 'fully_utilized'])
            ->when($this->customer, fn ($q) => $q->where('customer_id', $this->customer))
            ->with(['customer', 'proformaInvoices.lines', 'proformaInvoices.taxRate'])
            ->orderBy('expiry_date')
            ->get()
            ->filter(fn (LetterOfCredit $lc): bool => $lc->remaining()->isPositive())
            ->map(fn (LetterOfCredit $lc): array => [
                $lc->number,
                $lc->customer?->name ?? '—',
                $lc->currency_code,
                number_format((float) $lc->amount, 2),
                number_format((float) (string) $lc->allocated(), 2),
                number_format((float) (string) $lc->remaining(), 2),
                $lc->expiry_date?->toDateString() ?? '—',
                $lc->isPastExpiry() ? 'EXPIRED' : $lc->status->label(),
            ])->values()->all();
    }
}
