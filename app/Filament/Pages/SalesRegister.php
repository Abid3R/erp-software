<?php

namespace App\Filament\Pages;

use App\Domain\Reporting\SalesRegister as SalesRegisterReport;
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
 * Sales register (spec: Reports — Phase 15). Sales orders in a period with ordered
 * and delivered value, from {@see SalesRegisterReport}.
 */
class SalesRegister extends Page implements HasForms
{
    use HasPageShield;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Order Processing';

    protected static ?string $navigationLabel = 'Sales Register';

    protected static ?int $navigationSort = 7;

    protected static string $view = 'filament.pages.sales-register';

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

    /** @return array{rows: list<array<string, string>>, ordered: string, delivered: string} */
    public function getRegister(): array
    {
        $companyId = app(CompanyContext::class)->currentId();
        if ($companyId === null) {
            return ['rows' => [], 'ordered' => '0.00', 'delivered' => '0.00'];
        }

        return SalesRegisterReport::for($companyId, $this->from, $this->to);
    }

    public function downloadPdf(): Response
    {
        $company = app(CompanyContext::class)->current();
        abort_if($company === null, 400, 'No active company selected.');

        $pdf = Pdf::loadView('reports.sales-register-pdf', [
            'register' => $this->getRegister(),
            'company' => $company,
            'setting' => $company->reportSettingOrNew(),
            'from' => $this->from,
            'to' => $this->to,
        ]);

        return response()->streamDownload(fn () => print ($pdf->output()), "sales-register-{$company->code}.pdf");
    }

    public function money(string $value): string
    {
        return config('erp.currency.symbol').number_format((float) $value, (int) config('erp.currency.precision', 2));
    }
}
