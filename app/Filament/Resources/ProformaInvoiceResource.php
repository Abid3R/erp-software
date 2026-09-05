<?php

namespace App\Filament\Resources;

use App\Actions\Export\AllocateProformaToLc;
use App\Actions\Export\CreateCommercialInvoiceFromSource;
use App\Enums\ProformaInvoiceStatus;
use App\Exceptions\ExportException;
use App\Filament\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\ProformaInvoiceResource\Pages;
use App\Models\LetterOfCredit;
use App\Models\ProformaInvoice;
use App\Support\CompanyContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/**
 * Proforma Invoice (spec: Export). A pre-shipment commercial document, usually
 * pulled from a Sales Order and allocated to a Letter of Credit. Informational —
 * no inventory or ledger impact. From here a user can allocate to an LC or raise a
 * Commercial Invoice.
 */
class ProformaInvoiceResource extends Resource
{
    protected static ?string $model = ProformaInvoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Export';

    protected static ?string $navigationLabel = 'Proforma Invoices';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::query()->whereIn('status', ['draft', 'sent'])->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Proforma invoice')->columns(2)->schema([
                Forms\Components\TextInput::make('number')->label('PI No.')->maxLength(32)
                    ->placeholder('Auto (PI-0001) if left blank')
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('company_id', app(CompanyContext::class)->currentId())),
                Forms\Components\DatePicker::make('pi_date')->default(now())->required(),
                Forms\Components\Select::make('customer_id')->relationship('customer', 'name')->searchable()->preload()->required(),
                Forms\Components\Select::make('sales_order_id')->label('Sales order (optional)')
                    ->relationship('salesOrder', 'so_number')->searchable()->preload(),
                Forms\Components\Select::make('letter_of_credit_id')->label('Letter of credit (optional)')
                    ->relationship('letterOfCredit', 'number')->searchable()->preload()
                    ->helperText('Or allocate later from the list via “Allocate to LC”.'),
                Forms\Components\Select::make('warehouse_id')->relationship('warehouse', 'name')->searchable()->preload(),
            ]),
            Forms\Components\Section::make('Currency & terms')->columns(3)->schema([
                Forms\Components\Select::make('currency_code')->label('Currency')
                    ->options(fn (): array => LetterOfCreditResource::currencyOptions())
                    ->default(config('erp.export.default_currency'))->required(),
                Forms\Components\TextInput::make('exchange_rate')->label('Exchange rate → base')
                    ->numeric()->minValue(0)->default(1)->required(),
                Forms\Components\Select::make('incoterm')->options(fn (): array => LetterOfCreditResource::incotermOptions())
                    ->default(config('erp.export.default_incoterm')),
                Forms\Components\Select::make('payment_terms')->options(fn (): array => LetterOfCreditResource::paymentTermOptions())->searchable(),
                Forms\Components\TextInput::make('delivery_terms')->maxLength(191),
                Forms\Components\TextInput::make('discount')->numeric()->minValue(0)->default(0),
                Forms\Components\Select::make('tax_rate_id')->label('Tax / VAT')
                    ->relationship('taxRate', 'name')->searchable()->preload()
                    ->helperText('Leave empty for zero-rated export.'),
            ]),
            Forms\Components\Section::make('Items')->schema([
                Forms\Components\Repeater::make('lines')->relationship()->schema([
                    Forms\Components\Select::make('product_id')->label('Product')
                        ->relationship('product', 'name')->searchable()->preload()->required(),
                    Forms\Components\TextInput::make('description')->maxLength(191),
                    Forms\Components\TextInput::make('quantity')->numeric()->minValue(0.0001)->required(),
                    Forms\Components\TextInput::make('unit_price')->numeric()->minValue(0)->required(),
                ])->columns(4)->minItems(1)->addActionLabel('Add item')->columnSpanFull(),
            ]),
            Forms\Components\Textarea::make('notes')->rows(2)->maxLength(500)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')->label('PI No.')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('pi_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('customer.name')->searchable(),
                Tables\Columns\TextColumn::make('letterOfCredit.number')->label('LC')->placeholder('—'),
                Tables\Columns\TextColumn::make('currency_code')->label('Ccy'),
                Tables\Columns\TextColumn::make('total')->label('Total')
                    ->state(fn (ProformaInvoice $record): string => number_format((float) (string) $record->total(), 2)),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (ProformaInvoiceStatus $state): string => $state->label())
                    ->color(fn (ProformaInvoiceStatus $state): string => $state->color()),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(ProformaInvoiceStatus::options()),
                Tables\Filters\SelectFilter::make('customer_id')->relationship('customer', 'name')->searchable()->preload(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (ProformaInvoice $record): bool => $record->status === ProformaInvoiceStatus::Draft),

                Tables\Actions\Action::make('send')->label('Mark sent')->icon('heroicon-o-paper-airplane')->color('info')
                    ->visible(fn (ProformaInvoice $record): bool => $record->status === ProformaInvoiceStatus::Draft)
                    ->requiresConfirmation()
                    ->action(function (ProformaInvoice $record): void {
                        $record->update(['status' => ProformaInvoiceStatus::Sent]);
                        Notification::make()->title('Proforma marked as sent')->success()->send();
                    }),

                Tables\Actions\Action::make('approve')->label('Approve')->icon('heroicon-o-check-badge')->color('success')
                    ->visible(fn (ProformaInvoice $record): bool => in_array($record->status, [ProformaInvoiceStatus::Draft, ProformaInvoiceStatus::Sent], true))
                    ->requiresConfirmation()
                    ->action(function (ProformaInvoice $record): void {
                        $record->update(['status' => ProformaInvoiceStatus::Approved]);
                        Notification::make()->title('Proforma approved')->body('It can now be allocated to an LC or invoiced.')->success()->send();
                    }),

                Tables\Actions\Action::make('allocate')->label('Allocate to LC')->icon('heroicon-o-banknotes')->color('warning')
                    ->visible(fn (ProformaInvoice $record): bool => $record->status === ProformaInvoiceStatus::Approved)
                    ->form([
                        Forms\Components\Select::make('letter_of_credit_id')->label('Letter of credit')
                            ->options(fn (): array => LetterOfCredit::query()->whereNotIn('status', ['cancelled', 'expired'])
                                ->orderByDesc('id')->pluck('number', 'id')->all())
                            ->searchable()->required(),
                    ])
                    ->action(function (ProformaInvoice $record, array $data): void {
                        $lc = LetterOfCredit::query()->find($data['letter_of_credit_id']);
                        if ($lc === null) {
                            Notification::make()->title('LC not found')->danger()->send();

                            return;
                        }
                        try {
                            app(AllocateProformaToLc::class)->handle($record, $lc, allowOverride: (bool) Auth::user()?->hasRole('super_admin'));
                            Notification::make()->title('Allocated to '.$lc->number)->body('Remaining LC: '.number_format((float) (string) $lc->refresh()->remaining(), 2))->success()->send();
                        } catch (ExportException $e) {
                            Notification::make()->title('Cannot allocate')->body($e->getMessage())->danger()->send();
                        }
                    }),

                Tables\Actions\Action::make('createCi')->label('Create commercial invoice')->icon('heroicon-o-document-currency-dollar')->color('gray')
                    ->visible(fn (ProformaInvoice $record): bool => $record->status === ProformaInvoiceStatus::Approved)
                    ->requiresConfirmation()
                    ->modalDescription('Creates a draft commercial invoice carrying this PI’s customer, currency, references and lines.')
                    ->action(function (ProformaInvoice $record): void {
                        $ci = app(CreateCommercialInvoiceFromSource::class)->fromProforma($record);
                        Notification::make()->title('Commercial invoice '.$ci->number.' created')
                            ->body('Open Export → Commercial Invoices to review, approve and post it.')->success()->send();
                    }),

                Tables\Actions\Action::make('print')->icon('heroicon-o-printer')->color('gray')
                    ->url(fn (ProformaInvoice $record): string => route('print.proforma-invoice', $record))->openUrlInNewTab(),

                Tables\Actions\Action::make('cancel')->icon('heroicon-o-x-mark')->color('danger')
                    ->visible(fn (ProformaInvoice $record): bool => $record->status !== ProformaInvoiceStatus::Cancelled)
                    ->requiresConfirmation()
                    ->action(fn (ProformaInvoice $record) => $record->update(['status' => ProformaInvoiceStatus::Cancelled])),
            ])
            ->defaultSort('pi_date', 'desc');
    }

    public static function getRelations(): array
    {
        return [DocumentsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProformaInvoices::route('/'),
            'create' => Pages\CreateProformaInvoice::route('/create'),
            'view' => Pages\ViewProformaInvoice::route('/{record}'),
            'edit' => Pages\EditProformaInvoice::route('/{record}/edit'),
        ];
    }
}
