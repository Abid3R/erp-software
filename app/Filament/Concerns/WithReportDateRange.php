<?php

namespace App\Filament\Concerns;

use App\Support\ReportRange;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;

/**
 * Adds a consistent date-range filter (Today / This Month / This Year / Custom)
 * to a report page. The Custom date pickers only show when the preset is Custom.
 * Pages keep their own `form()` and merge {@see rangeSchema()} into it.
 */
trait WithReportDateRange
{
    public ?string $range = ReportRange::THIS_MONTH;

    public ?string $from = null;

    public ?string $to = null;

    /** @return array<int, \Filament\Forms\Components\Component> */
    protected function rangeSchema(): array
    {
        return [
            Select::make('range')
                ->label('Date range')
                ->options(ReportRange::options())
                ->default(ReportRange::THIS_MONTH)
                ->live()
                ->selectablePlaceholder(false),
            DatePicker::make('from')->label('From')->live()
                ->visible(fn (): bool => $this->range === ReportRange::CUSTOM),
            DatePicker::make('to')->label('To')->live()
                ->visible(fn (): bool => $this->range === ReportRange::CUSTOM),
        ];
    }

    /** @return array{from: string|null, to: string|null} */
    protected function resolvedRange(): array
    {
        return ReportRange::resolve($this->range, $this->from, $this->to);
    }

    /** Human-readable label for the currently active range (for headers/filenames). */
    public function rangeLabel(): string
    {
        ['from' => $from, 'to' => $to] = $this->resolvedRange();

        return ($from ?? 'Beginning').' to '.($to ?? 'Present');
    }
}
