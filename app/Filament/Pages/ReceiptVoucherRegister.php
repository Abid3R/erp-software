<?php

namespace App\Filament\Pages;

use App\Domain\Reporting\VoucherRegister;
use App\Filament\Concerns\WithReportDateRange;
use App\Models\Customer;
use App\Support\CompanyContext;
use App\Support\CsvExport;
use Barryvdh\DomPDF\Facade\Pdf;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Receipt Voucher Register (spec: Reports — Phase 15). Customer receipts in a
 * period, from {@see VoucherRegister}. Totals reconcile with the cash/bank GL
 * movement and AR settlements.
 */
class ReceiptVoucherRegister extends Page implements HasForms
{
    use HasPageShield;
    use InteractsWithForms;
    use WithReportDateRange;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Accounts';

    protected static ?string $navigationLabel = 'Receipt Vouchers';

    protected static ?int $navigationSort = 12;

    protected static string $view = 'filament.pages.receipt-voucher-register';

    public ?int $partyId = null;

    public function form(Form $form): Form
    {
        return $form->schema([
            ...$this->rangeSchema(),
            Select::make('partyId')->label('Customer')
                ->options(fn (): array => $this->customerOptions())
                ->searchable()->preload()->placeholder('All customers')->live(),
        ])->columns(4)->statePath('');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pdf')->label('Download PDF')->icon('heroicon-o-arrow-down-tray')
                ->action(fn (): Response => $this->downloadPdf()),
            Action::make('csv')->label('Export CSV')->icon('heroicon-o-table-cells')->color('gray')
                ->action(fn (): StreamedResponse => $this->downloadCsv()),
        ];
    }

    /** @return array{rows: list<array<string, string>>, total: string, count: int} */
    public function getRegister(): array
    {
        $companyId = app(CompanyContext::class)->currentId();
        if ($companyId === null) {
            return ['rows' => [], 'total' => '0.00', 'count' => 0];
        }

        ['from' => $from, 'to' => $to] = $this->resolvedRange();

        return VoucherRegister::receipts($companyId, $from, $to, $this->partyId);
    }

    public function downloadPdf(): Response
    {
        $company = app(CompanyContext::class)->current();
        abort_if($company === null, 400, 'No active company selected.');

        ['from' => $from, 'to' => $to] = $this->resolvedRange();

        $pdf = Pdf::loadView('reports.voucher-register-pdf', [
            'register' => $this->getRegister(),
            'company' => $company,
            'setting' => $company->reportSettingOrNew(),
            'title' => 'Receipt Voucher Register',
            'partyLabel' => 'Customer',
            'from' => $from,
            'to' => $to,
            'generatedBy' => auth()->user()?->name ?? 'system',
        ]);

        return response()->streamDownload(fn () => print ($pdf->output()), "receipt-vouchers-{$company->code}.pdf");
    }

    public function downloadCsv(): StreamedResponse
    {
        $company = app(CompanyContext::class)->current();
        abort_if($company === null, 400, 'No active company selected.');

        $register = $this->getRegister();
        $rows = array_map(fn (array $r): array => [
            $r['date'], $r['reference'], $r['party'], $r['method'], $r['note'], $r['amount'],
        ], $register['rows']);
        $rows[] = ['', '', '', '', 'Total', $register['total']];

        return CsvExport::stream(
            ['Date', 'Voucher/Ref', 'Customer', 'Method', 'Note', 'Amount (BDT)'],
            $rows,
            "receipt-vouchers-{$company->code}.csv",
        );
    }

    public function money(string $value): string
    {
        return config('erp.currency.symbol').number_format((float) $value, (int) config('erp.currency.precision', 2));
    }

    /** @return array<int, string> */
    private function customerOptions(): array
    {
        return Customer::query()->orderBy('name')->pluck('name', 'id')
            ->map(fn ($n): string => (string) $n)->all();
    }
}
