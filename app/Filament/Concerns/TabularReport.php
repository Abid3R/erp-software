<?php

namespace App\Filament\Concerns;

use App\Support\CompanyContext;
use App\Support\CsvExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Shared plumbing for simple tabular report pages: a header (PDF + CSV) toolbar,
 * an A4 PDF via the common report layout, and CSV export — all driven by three
 * methods the page implements. Keeps every textile report consistent and avoids a
 * bespoke view/exporter per report.
 */
trait TabularReport
{
    /** The report's display title. */
    abstract public function reportTitle(): string;

    /** @return list<string> column headers */
    abstract public function reportHeaders(): array;

    /** @return list<list<string>> already-formatted rows */
    abstract public function reportRows(): array;

    public function reportPeriod(): string
    {
        return method_exists($this, 'rangeLabel') ? 'Period: '.$this->rangeLabel() : 'As of: '.now()->toDateString();
    }

    /** @return list<Action> */
    protected function tabularActions(): array
    {
        return [
            Action::make('pdf')->label('Download PDF')->icon('heroicon-o-arrow-down-tray')
                ->action(fn (): Response => $this->downloadPdf()),
            Action::make('csv')->label('Export CSV')->icon('heroicon-o-table-cells')->color('gray')
                ->action(fn (): StreamedResponse => $this->downloadCsv()),
        ];
    }

    public function downloadPdf(): Response
    {
        $company = app(CompanyContext::class)->current();
        abort_if($company === null, 400, 'No active company selected.');

        $pdf = Pdf::loadView('reports.tabular-pdf', [
            'title' => $this->reportTitle(),
            'period' => $this->reportPeriod(),
            'headers' => $this->reportHeaders(),
            'rows' => $this->reportRows(),
            'company' => $company,
            'setting' => $company->reportSettingOrNew(),
        ])->setPaper('a4', count($this->reportHeaders()) > 6 ? 'landscape' : 'portrait');

        $slug = Str::slug($this->reportTitle());

        return response()->streamDownload(fn () => print ($pdf->output()), "{$slug}-{$company->code}.pdf");
    }

    public function downloadCsv(): StreamedResponse
    {
        $company = app(CompanyContext::class)->current();
        abort_if($company === null, 400, 'No active company selected.');

        $slug = Str::slug($this->reportTitle());

        return CsvExport::stream($this->reportHeaders(), $this->reportRows(), "{$slug}-{$company->code}.csv");
    }

    protected function money(string|float|int|null $value): string
    {
        return config('erp.currency.symbol').number_format((float) $value, (int) config('erp.currency.precision', 2));
    }

    protected function qty(string|float|int|null $value): string
    {
        return rtrim(rtrim((string) $value, '0'), '.') ?: '0';
    }
}
