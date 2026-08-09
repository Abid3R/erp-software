<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\Response;

/**
 * Read-only payments list (receipts + supplier vouchers) with a printable voucher
 * (spec #23, #31). Payments are created through the payment actions; this screen
 * surfaces and prints them.
 */
class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Payments';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['party', 'journal']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('date')->date()->sortable(),
                Tables\Columns\TextColumn::make('direction')->badge()
                    ->formatStateUsing(fn ($state): string => $state->label())
                    ->colors(['success' => 'receipt', 'warning' => 'payment']),
                Tables\Columns\TextColumn::make('party.name')->label('Party')->default('—'),
                Tables\Columns\TextColumn::make('amount')->money(config('erp.currency.code'))->sortable(),
                Tables\Columns\TextColumn::make('method')->badge()
                    ->formatStateUsing(fn ($state): string => $state->label()),
                Tables\Columns\TextColumn::make('reference')->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('direction')->options([
                    'receipt' => 'Receipt', 'payment' => 'Payment',
                ]),
            ])
            ->actions([
                Tables\Actions\Action::make('print')
                    ->icon('heroicon-o-printer')
                    ->url(fn (Payment $record): string => route('print.payment', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('pdf')
                    ->label('PDF')->icon('heroicon-o-arrow-down-tray')
                    ->action(function (Payment $record): Response {
                        $pdf = Pdf::loadView('reports.payment-pdf', [
                            'payment' => $record->load('party', 'journal'),
                            'company' => $record->company,
                        ]);

                        return response()->streamDownload(fn () => print ($pdf->output()), "voucher-{$record->getKey()}.pdf");
                    }),
            ])
            ->defaultSort('date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
        ];
    }
}
