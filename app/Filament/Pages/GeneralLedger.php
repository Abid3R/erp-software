<?php

namespace App\Filament\Pages;

use App\Domain\Reporting\GeneralLedger as GeneralLedgerReport;
use App\Models\Account;
use Barryvdh\DomPDF\Facade\Pdf;
use Brick\Math\BigDecimal;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Symfony\Component\HttpFoundation\Response;

/**
 * General Ledger for a chosen account and date range, with running balance and
 * PDF/CSV export (spec #31). Derived from the posted ledger.
 */
class GeneralLedger extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-list-bullet';

    protected static ?string $navigationGroup = 'Accounting';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'General Ledger';

    protected static string $view = 'filament.pages.general-ledger';

    public ?int $account_id = null;

    public ?string $from = null;

    public ?string $to = null;

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('account_id')
                    ->label('Account')
                    ->options(fn (): array => Account::query()->orderBy('code')->get()
                        ->mapWithKeys(fn (Account $a) => [$a->getKey() => "{$a->code} — {$a->name}"])->all())
                    ->searchable()->live(),
                DatePicker::make('from')->label('From')->live(),
                DatePicker::make('to')->label('To')->live(),
            ])
            ->columns(3)
            ->statePath('');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pdf')->label('PDF')->icon('heroicon-o-document')
                ->visible(fn (): bool => $this->account() !== null)
                ->action(fn (): Response => $this->downloadPdf()),
            Action::make('csv')->label('CSV')->icon('heroicon-o-table-cells')
                ->visible(fn (): bool => $this->account() !== null)
                ->action(fn (): Response => $this->downloadCsv()),
        ];
    }

    public function account(): ?Account
    {
        return $this->account_id === null ? null : Account::query()->find($this->account_id);
    }

    /** @return array{opening: BigDecimal, closing: BigDecimal, lines: list<array<string, mixed>>}|null */
    public function getLedger(): ?array
    {
        $account = $this->account();

        return $account === null ? null : GeneralLedgerReport::forAccount($account, $this->from, $this->to);
    }

    public function downloadPdf(): Response
    {
        $account = $this->account();
        $pdf = Pdf::loadView('reports.general-ledger-pdf', [
            'account' => $account,
            'company' => $account?->company,
            'ledger' => $this->getLedger(),
            'from' => $this->from,
            'to' => $this->to,
        ]);

        return response()->streamDownload(fn () => print ($pdf->output()), "general-ledger-{$account?->code}.pdf");
    }

    public function downloadCsv(): Response
    {
        $ledger = $this->getLedger();
        $account = $this->account();

        return response()->streamDownload(function () use ($ledger): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Journal', 'Memo', 'Debit', 'Credit', 'Running']);
            fputcsv($out, ['Opening', '', '', '', '', (string) ($ledger['opening'] ?? '0')]);
            foreach ($ledger['lines'] ?? [] as $line) {
                fputcsv($out, [$line['date'], $line['journal_id'], $line['memo'], $line['debit'], $line['credit'], $line['running']]);
            }
            fputcsv($out, ['Closing', '', '', '', '', (string) ($ledger['closing'] ?? '0')]);
            fclose($out);
        }, "general-ledger-{$account?->code}.csv");
    }

    public function money(BigDecimal|string $value): string
    {
        return config('erp.currency.symbol').number_format((float) (string) $value, (int) config('erp.currency.precision', 2));
    }
}
