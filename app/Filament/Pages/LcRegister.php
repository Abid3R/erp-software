<?php

namespace App\Filament\Pages;

use App\Enums\LetterOfCreditStatus;
use App\Filament\Concerns\TabularReport;
use App\Filament\Concerns\WithReportDateRange;
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
 * LC Register — letters of credit opened in a period, with live allocated /
 * remaining figures. Real data from letters_of_credit.
 */
class LcRegister extends Page implements HasForms
{
    use HasPageShield;
    use InteractsWithForms;
    use TabularReport;
    use WithReportDateRange;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pages.tabular-report';

    protected static ?string $title = 'LC Register';

    public ?int $customer = null;

    public ?string $status = null;

    public function form(Form $form): Form
    {
        return $form->schema([
            ...$this->rangeSchema(),
            Select::make('customer')->label('Applicant')
                ->options(fn (): array => Customer::query()->orderBy('name')->pluck('name', 'id')->all())
                ->placeholder('All customers')->live(),
            Select::make('status')->options(LetterOfCreditStatus::options())->placeholder('All statuses')->live(),
        ])->columns(4)->statePath('');
    }

    protected function getHeaderActions(): array
    {
        return $this->tabularActions();
    }

    public function reportTitle(): string
    {
        return 'LC Register';
    }

    public function reportHeaders(): array
    {
        return ['LC Date', 'LC No.', 'Applicant', 'Issuing bank', 'Ccy', 'Amount', 'Allocated', 'Remaining', 'Status'];
    }

    public function reportRows(): array
    {
        $companyId = app(CompanyContext::class)->currentId();
        if ($companyId === null) {
            return [];
        }
        ['from' => $from, 'to' => $to] = $this->resolvedRange();

        return LetterOfCredit::query()->where('company_id', $companyId)
            ->when($from, fn ($q) => $q->whereDate('lc_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('lc_date', '<=', $to))
            ->when($this->customer, fn ($q) => $q->where('customer_id', $this->customer))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->with(['customer', 'proformaInvoices.lines', 'proformaInvoices.taxRate'])
            ->orderBy('lc_date')
            ->get()
            ->map(fn (LetterOfCredit $lc): array => [
                $lc->lc_date->toDateString(),
                $lc->number,
                $lc->customer?->name ?? '—',
                $lc->issuing_bank ?? '—',
                $lc->currency_code,
                number_format((float) $lc->amount, 2),
                number_format((float) (string) $lc->allocated(), 2),
                number_format((float) (string) $lc->remaining(), 2),
                $lc->status->label(),
            ])->all();
    }
}
