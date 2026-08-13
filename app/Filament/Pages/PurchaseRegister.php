<?php

namespace App\Filament\Pages;

use App\Domain\Reporting\PurchaseRegister as PurchaseRegisterReport;
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
 * Purchase register (spec: Reports — Phase 15). Supplier invoices in a period with
 * their net value, from {@see PurchaseRegisterReport}.
 */
class PurchaseRegister extends Page implements HasForms
{
    use HasPageShield;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Purchasing';

    protected static ?string $navigationLabel = 'Purchase Register';

    protected static ?int $navigationSort = 6;

    protected static string $view = 'filament.pages.purchase-register';

    public ?string $from = null;

    public ?string $to = null;

    public function form(Form $form): Form
    {
        return $form->schema([
            DatePicker::make('from')->live(),
            DatePicker::make('to')->live(),
        ])->columns(2)->statePath('');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pdf')->label('Download PDF')->icon('heroicon-o-arrow-down-tray')
                ->action(fn (): Response => $this->downloadPdf()),
        ];
    }

    /** @return array{rows: list<array<string, string>>, net: string} */
    public function getRegister(): array
    {
        $companyId = app(CompanyContext::class)->currentId();
        if ($companyId === null) {
            return ['rows' => [], 'net' => '0.00'];
        }

        return PurchaseRegisterReport::for($companyId, $this->from, $this->to);
    }

    public function downloadPdf(): Response
    {
        $company = app(CompanyContext::class)->current();
        abort_if($company === null, 400, 'No active company selected.');

        $pdf = Pdf::loadView('reports.purchase-register-pdf', [
            'register' => $this->getRegister(),
            'company' => $company,
            'setting' => $company->reportSettingOrNew(),
            'from' => $this->from,
            'to' => $this->to,
        ]);

        return response()->streamDownload(fn () => print ($pdf->output()), "purchase-register-{$company->code}.pdf");
    }

    public function money(string $value): string
    {
        return config('erp.currency.symbol').number_format((float) $value, (int) config('erp.currency.precision', 2));
    }
}
