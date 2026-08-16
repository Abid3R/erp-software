<?php

namespace App\Filament\Resources;

use App\Filament\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\Response;

/**
 * Payments list (receipts + supplier vouchers) with a printable voucher (spec #23,
 * #31). Posted rows stay immutable — creation happens via the "Record receipt" /
 * "Record payment" header actions on the list page, which call the idempotent
 * payment actions; there is no row edit/delete of a posted payment.
 */
class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Accounts';

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['party', 'journal']);
    }

    /**
     * Read-only form used by the View page. Payment posting happens through the
     * receipt/payment header actions; there is no create/edit here.
     */
    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('direction')->disabled(),
            Forms\Components\DatePicker::make('date')->disabled(),
            Forms\Components\TextInput::make('party.name')->label('Party')->disabled(),
            Forms\Components\TextInput::make('amount')->prefix(config('erp.currency.symbol'))->disabled(),
            Forms\Components\TextInput::make('method')->disabled(),
            Forms\Components\TextInput::make('reference')->disabled(),
            Forms\Components\Textarea::make('note')->disabled()->columnSpanFull(),
        ])->columns(2)->disabled();
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
                Tables\Actions\ViewAction::make(),
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
                            'setting' => $record->company?->reportSettingOrNew(),
                        ]);

                        return response()->streamDownload(fn () => print ($pdf->output()), "voucher-{$record->getKey()}.pdf");
                    }),
            ])
            ->defaultSort('date', 'desc');
    }

    public static function getRelations(): array
    {
        return [DocumentsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
            'view' => Pages\ViewPayment::route('/{record}'),
        ];
    }
}
