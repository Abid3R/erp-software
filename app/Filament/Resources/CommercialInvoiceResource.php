<?php

namespace App\Filament\Resources;

use App\Actions\Export\CancelCommercialInvoice;
use App\Actions\Export\GeneratePackingListFromInvoice;
use App\Actions\Export\PostCommercialInvoice;
use App\Enums\CommercialInvoiceStatus;
use App\Exceptions\ExportException;
use App\Exceptions\PostingException;
use App\Filament\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\CommercialInvoiceResource\Pages;
use App\Models\CommercialInvoice;
use App\Support\CompanyContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Commercial Invoice (spec: Export). The legal export invoice — the one export
 * document that posts to the ledger. On posting it books AR in the base currency
 * (Dr Receivable / Cr Sales / Cr Output-VAT); COGS was already booked by the
 * linked Delivery Order. Corrections are made by reversal (Cancel), never edits.
 */
class CommercialInvoiceResource extends Resource
{
    protected static ?string $model = CommercialInvoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-currency-dollar';

    protected static ?string $navigationGroup = 'Export';

    protected static ?string $navigationLabel = 'Commercial Invoices';

    protected static ?int $navigationSort = 3;

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::query()->whereIn('status', ['draft', 'approved'])->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Commercial invoice')->columns(2)->schema([
                Forms\Components\TextInput::make('number')->label('Invoice No.')->maxLength(32)
                    ->placeholder('Auto (CI-0001) if left blank')
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('company_id', app(CompanyContext::class)->currentId())),
                Forms\Components\DatePicker::make('invoice_date')->default(now())->required(),
                Forms\Components\Select::make('customer_id')->relationship('customer', 'name')->searchable()->preload()->required(),
                Forms\Components\TextInput::make('consignee')->maxLength(191)->helperText('Buyer / consignee if different from customer.'),
                Forms\Components\Select::make('proforma_invoice_id')->label('Proforma invoice')
                    ->relationship('proformaInvoice', 'number')->searchable()->preload(),
                Forms\Components\Select::make('letter_of_credit_id')->label('Letter of credit')
                    ->relationship('letterOfCredit', 'number')->searchable()->preload(),
                Forms\Components\Select::make('sales_order_id')->label('Sales order')
                    ->relationship('salesOrder', 'so_number')->searchable()->preload(),
                Forms\Components\Select::make('delivery_order_id')->label('Delivery order')
                    ->relationship('deliveryOrder', 'number')->searchable()->preload(),
            ]),
            Forms\Components\Section::make('Currency, origin & terms')->columns(3)->schema([
                Forms\Components\Select::make('currency_code')->label('Currency')
                    ->options(fn (): array => LetterOfCreditResource::currencyOptions())
                    ->default(config('erp.export.default_currency'))->required(),
                Forms\Components\TextInput::make('exchange_rate')->label('Exchange rate → base')
                    ->numeric()->minValue(0.000001)->default(1)->required()
                    ->helperText('AR posts in '.config('erp.currency.code').' at this rate.'),
                Forms\Components\Select::make('incoterm')->options(fn (): array => LetterOfCreditResource::incotermOptions())
                    ->default(config('erp.export.default_incoterm')),
                Forms\Components\TextInput::make('country_of_origin')->maxLength(96)->default('Bangladesh'),
                Forms\Components\TextInput::make('destination_country')->maxLength(96),
                Forms\Components\Select::make('payment_terms')->options(fn (): array => LetterOfCreditResource::paymentTermOptions())->searchable(),
                Forms\Components\TextInput::make('discount')->numeric()->minValue(0)->default(0),
                Forms\Components\Select::make('tax_rate_id')->label('Tax / VAT')
                    ->relationship('taxRate', 'name')->searchable()->preload()
                    ->helperText('Leave empty for zero-rated export.'),
            ]),
            Forms\Components\Section::make('Items')->schema([
                Forms\Components\Repeater::make('lines')->relationship()->schema([
                    Forms\Components\Select::make('product_id')->label('Product')
                        ->relationship('product', 'name')->searchable()->preload()->required(),
                    Forms\Components\TextInput::make('hs_code')->label('HS code')->maxLength(32),
                    Forms\Components\TextInput::make('quantity')->numeric()->minValue(0.0001)->required(),
                    Forms\Components\TextInput::make('unit')->maxLength(16),
                    Forms\Components\TextInput::make('unit_price')->numeric()->minValue(0)->required(),
                ])->columns(5)->minItems(1)->addActionLabel('Add item')->columnSpanFull(),
            ]),
            Forms\Components\Textarea::make('terms')->label('Terms & conditions')->rows(2)->maxLength(500)->columnSpanFull(),
            Forms\Components\Textarea::make('notes')->rows(2)->maxLength(500)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')->label('Invoice No.')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('invoice_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('customer.name')->searchable(),
                Tables\Columns\TextColumn::make('letterOfCredit.number')->label('LC')->placeholder('—'),
                Tables\Columns\TextColumn::make('currency_code')->label('Ccy'),
                Tables\Columns\TextColumn::make('total')->label('Total')
                    ->state(fn (CommercialInvoice $record): string => number_format((float) (string) $record->total(), 2)),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (CommercialInvoiceStatus $state): string => $state->label())
                    ->color(fn (CommercialInvoiceStatus $state): string => $state->color()),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(CommercialInvoiceStatus::options()),
                Tables\Filters\SelectFilter::make('customer_id')->relationship('customer', 'name')->searchable()->preload(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (CommercialInvoice $record): bool => $record->status->isEditable()),

                Tables\Actions\Action::make('approve')->label('Approve')->icon('heroicon-o-check-badge')->color('info')
                    ->visible(fn (CommercialInvoice $record): bool => $record->status === CommercialInvoiceStatus::Draft)
                    ->requiresConfirmation()
                    ->action(function (CommercialInvoice $record): void {
                        $record->update(['status' => CommercialInvoiceStatus::Approved]);
                        Notification::make()->title('Invoice approved')->body('It can now be posted to the ledger.')->success()->send();
                    }),

                Tables\Actions\Action::make('post')->label('Post AR')->icon('heroicon-o-banknotes')->color('success')
                    ->visible(fn (CommercialInvoice $record): bool => $record->status === CommercialInvoiceStatus::Approved)
                    ->requiresConfirmation()
                    ->modalDescription('Books the receivable in the base currency (Dr Receivable / Cr Sales / Cr Output-VAT). COGS was already booked by the delivery order. Reversible via Cancel.')
                    ->action(function (CommercialInvoice $record): void {
                        try {
                            app(PostCommercialInvoice::class)->handle($record);
                            Notification::make()->title('Invoice posted')->body('Receivable booked for '.$record->customer?->name.'.')->success()->send();
                        } catch (ExportException|PostingException $e) {
                            Notification::make()->title('Cannot post invoice')->body($e->getMessage())->danger()->send();
                        }
                    }),

                Tables\Actions\Action::make('packingList')->label('Generate packing list')->icon('heroicon-o-clipboard-document-list')->color('gray')
                    ->visible(fn (CommercialInvoice $record): bool => in_array($record->status, [CommercialInvoiceStatus::Approved, CommercialInvoiceStatus::Posted], true))
                    ->requiresConfirmation()
                    ->action(function (CommercialInvoice $record): void {
                        $pl = app(GeneratePackingListFromInvoice::class)->handle($record);
                        Notification::make()->title('Packing list '.$pl->number.' created')->success()->send();
                    }),

                Tables\Actions\Action::make('print')->icon('heroicon-o-printer')->color('gray')
                    ->url(fn (CommercialInvoice $record): string => route('print.commercial-invoice', $record))->openUrlInNewTab(),

                Tables\Actions\Action::make('cancel')->icon('heroicon-o-arrow-uturn-left')->color('danger')
                    ->visible(fn (CommercialInvoice $record): bool => $record->status !== CommercialInvoiceStatus::Cancelled)
                    ->requiresConfirmation()
                    ->modalDescription('If the invoice was posted, this books a reversing journal. Cannot be undone.')
                    ->form([Forms\Components\Textarea::make('reason')->maxLength(255)->placeholder('Reason (optional)')])
                    ->action(function (CommercialInvoice $record, array $data): void {
                        try {
                            app(CancelCommercialInvoice::class)->handle($record, $data['reason'] ?? null);
                            Notification::make()->title('Invoice cancelled')->body('Any posted AR has been reversed.')->success()->send();
                        } catch (ExportException|PostingException $e) {
                            Notification::make()->title('Cannot cancel invoice')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->defaultSort('invoice_date', 'desc');
    }

    public static function getRelations(): array
    {
        return [DocumentsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCommercialInvoices::route('/'),
            'create' => Pages\CreateCommercialInvoice::route('/create'),
            'view' => Pages\ViewCommercialInvoice::route('/{record}'),
            'edit' => Pages\EditCommercialInvoice::route('/{record}/edit'),
        ];
    }
}
