<?php

namespace App\Filament\Pages;

use App\Domain\Reporting\PartyAging;
use App\Support\CompanyContext;
use Barryvdh\DomPDF\Facade\Pdf;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Symfony\Component\HttpFoundation\Response;

/**
 * AR Aging (spec: Reports — Phase 15). Per-customer open receivables bucketed by age,
 * derived from the ledger via {@see PartyAging}; totals reconcile with the GL AR
 * control account. Presentation only.
 */
class ReceivablesAging extends Page implements HasForms
{
    use HasPageShield;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-trending-up';

    protected static ?string $navigationGroup = 'Accounting';

    protected static ?string $navigationLabel = 'AR Aging';

    protected static ?int $navigationSort = 4;

    protected static string $view = 'filament.pages.receivables-aging';

    public ?string $asOf = null;

    public function mount(): void
    {
        $this->asOf ??= now()->toDateString();
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            DatePicker::make('asOf')->label('As of')->live(),
        ])->statePath('');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pdf')->label('Download PDF')->icon('heroicon-o-arrow-down-tray')
                ->action(fn (): Response => $this->downloadPdf()),
        ];
    }

    /** @return array{rows: list<array<string, mixed>>, totals: array<string, string>} */
    public function getAging(): array
    {
        $companyId = app(CompanyContext::class)->currentId();
        if ($companyId === null) {
            return ['rows' => [], 'totals' => []];
        }

        return PartyAging::receivables($companyId, $this->asOf);
    }

    public function downloadPdf(): Response
    {
        $company = app(CompanyContext::class)->current();
        abort_if($company === null, 400, 'No active company selected.');

        $pdf = Pdf::loadView('reports.aging-pdf', [
            'title' => 'Receivables Aging',
            'aging' => $this->getAging(),
            'company' => $company,
            'setting' => $company->reportSettingOrNew(),
            'asOf' => $this->asOf,
            'partyLabel' => 'Customer',
        ]);

        return response()->streamDownload(fn () => print ($pdf->output()), "ar-aging-{$company->code}.pdf");
    }

    public function money(string $value): string
    {
        return config('erp.currency.symbol').number_format((float) $value, (int) config('erp.currency.precision', 2));
    }
}
