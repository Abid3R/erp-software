<?php

namespace App\Filament\Resources;

use App\Enums\JournalStatus;
use App\Filament\Resources\JournalResource\Pages;
use App\Models\Journal;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\Response;

/**
 * Read-only journal browser (spec #31). Journals are posted by the engine and are
 * immutable — no create/edit/delete here. The view modal shows the balanced lines.
 */
class JournalResource extends Resource
{
    protected static ?string $model = Journal::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Accounting';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Journals';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('lines.account');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('date')->date()->sortable(),
                Tables\Columns\TextColumn::make('reference')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('memo')->limit(40)->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('status')->badge()->colors([
                    'success' => 'posted', 'gray' => 'draft',
                ]),
                Tables\Columns\TextColumn::make('total')->label('Total')
                    ->money(config('erp.currency.code'))
                    ->state(fn (Journal $record): string => (string) $record->totalDebit()),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'posted' => 'Posted', 'draft' => 'Draft',
                ])->default(JournalStatus::Posted->value),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('print')
                    ->icon('heroicon-o-printer')
                    ->url(fn (Journal $record): string => route('print.journal', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('pdf')
                    ->label('PDF')->icon('heroicon-o-arrow-down-tray')
                    ->action(function (Journal $record): Response {
                        $pdf = Pdf::loadView('reports.journal-pdf', [
                            'journal' => $record->load('lines.account'),
                            'company' => $record->company,
                            'setting' => $record->company?->reportSettingOrNew(),
                        ]);

                        return response()->streamDownload(fn () => print ($pdf->output()), "journal-{$record->getKey()}.pdf");
                    }),
            ])
            ->defaultSort('date', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            TextEntry::make('date')->date(),
            TextEntry::make('reference')->placeholder('—'),
            TextEntry::make('memo')->placeholder('—')->columnSpanFull(),
            RepeatableEntry::make('lines')
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('account.code')->label('Account'),
                    TextEntry::make('account.name')->label('Name'),
                    TextEntry::make('debit')->money(config('erp.currency.code')),
                    TextEntry::make('credit')->money(config('erp.currency.code')),
                ])
                ->columns(4),
        ])->columns(2);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListJournals::route('/'),
        ];
    }
}
