<?php

namespace App\Filament\Pages;

use App\Domain\Reporting\PartyAgingDetail;
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
 * AP Aging (spec: Reports — Phase 15). Per-supplier open payables bucketed by age,
 * derived from the ledger via {@see PartyAging}; totals reconcile with the GL AP
 * control account. Presentation only.
 */
class PayablesAging extends Page implements HasForms
{
    use HasPageShield;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-trending-down';

    protected static ?string $navigationGroup = 'Accounting';

    protected static ?string $navigationLabel = 'AP Aging';

    protected static ?int $navigationSort = 5;

    protected static string $view = 'filament.pages.payables-aging';

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

    /** @return array{rows: list<array{party_id: int, party: string, document: string, date: string, original: string, paid: string, outstanding: string, bucket: string}>, totals: array<string, string>} */
    public function getAging(): array
    {
        $companyId = app(CompanyContext::class)->currentId();
        if ($companyId === null) {
            return ['rows' => [], 'totals' => ['current' => '0.00', 'd30' => '0.00', 'd60' => '0.00', 'd90plus' => '0.00', 'total' => '0.00']];
        }

        return PartyAgingDetail::payables($companyId, $this->asOf);
    }

    public function downloadPdf(): Response
    {
        $company = app(CompanyContext::class)->current();
        abort_if($company === null, 400, 'No active company selected.');

        $pdf = Pdf::loadView('reports.aging-detail-pdf', [
            'title' => 'Payables Aging',
            'aging' => $this->getAging(),
            'company' => $company,
            'setting' => $company->reportSettingOrNew(),
            'asOf' => $this->asOf,
            'partyLabel' => 'Supplier',
        ]);

        return response()->streamDownload(fn () => print ($pdf->output()), "ap-aging-{$company->code}.pdf");
    }

    public function money(string $value): string
    {
        return config('erp.currency.symbol').number_format((float) $value, (int) config('erp.currency.precision', 2));
    }
}
