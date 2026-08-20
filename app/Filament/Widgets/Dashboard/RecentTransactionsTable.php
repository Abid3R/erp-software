<?php

namespace App\Filament\Widgets\Dashboard;

use App\Filament\Resources\JournalResource;
use App\Models\Journal;
use App\Support\CompanyContext;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * Most recent posted journals — a single, authoritative "recent transactions"
 * view. Row totals come from the journal's own line aggregates (unchanged).
 */
class RecentTransactionsTable extends BaseWidget
{
    use HasWidgetShield;

    protected static ?string $heading = 'Recent Transactions';

    protected static ?int $sort = 22;

    protected int|string|array $columnSpan = ['default' => 'full', 'md' => 6];

    public function table(Table $table): Table
    {
        return $table
            ->query($this->baseQuery())
            ->emptyStateHeading('No transactions yet')
            ->emptyStateDescription('Post a journal, sale, or purchase to see it here.')
            ->emptyStateIcon('heroicon-o-document-text')
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->columns([
                Tables\Columns\TextColumn::make('date')->date()->sortable(),
                Tables\Columns\TextColumn::make('reference')->searchable()->limit(32),
                Tables\Columns\TextColumn::make('memo')->limit(60)->wrap()->placeholder('—'),
                Tables\Columns\TextColumn::make('source_type')
                    ->label('Source')
                    ->formatStateUsing(fn ($state): string => $state ? class_basename((string) $state) : '—'),
                Tables\Columns\TextColumn::make('lines_sum_debit')
                    ->label('Amount')
                    ->money(config('erp.currency.code'))
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->formatStateUsing(fn ($state) => is_object($state) ? $state->label() : ucfirst((string) $state))
                    ->color(fn ($state): string => (is_object($state) ? $state->value : $state) === 'posted' ? 'success' : 'gray'),
            ])
            ->recordUrl(fn (): string => JournalResource::getUrl())
            ->defaultSort('date', 'desc');
    }

    /**
     * @return Builder<Journal>
     */
    private function baseQuery(): Builder
    {
        $companyId = app(CompanyContext::class)->currentId();

        return Journal::query()
            ->when($companyId !== null, fn ($q) => $q->where('company_id', $companyId))
            ->withSum('lines as lines_sum_debit', 'debit');
    }
}
