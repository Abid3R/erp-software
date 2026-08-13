<?php

namespace App\Filament\Resources;

use App\Actions\Purchasing\PostSupplierInvoice;
use App\Enums\SupplierInvoiceStatus;
use App\Exceptions\PostingException;
use App\Exceptions\SupplierInvoiceException;
use App\Filament\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\SupplierInvoiceResource\Pages;
use App\Models\Account;
use App\Models\SupplierInvoice;
use App\Models\TaxRate;
use App\Support\CompanyContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SupplierInvoiceResource extends Resource
{
    protected static ?string $model = SupplierInvoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-currency-dollar';

    protected static ?string $navigationGroup = 'Purchasing';

    protected static ?string $navigationLabel = 'Supplier Invoices';

    protected static ?int $navigationSort = 5;

    /** @return array<int|string, string> */
    private static function accountOptions(): array
    {
        return Account::query()->where('is_postable', true)->where('is_active', true)->orderBy('code')
            ->get()->mapWithKeys(fn (Account $a): array => [$a->getKey() => "{$a->code} · {$a->name}"])->all();
    }

    /** @return array<string, string> Active tax codes for the current company. */
    private static function taxCodeOptions(): array
    {
        return TaxRate::query()->where('is_active', true)->orderBy('code')
            ->get()->mapWithKeys(fn (TaxRate $t): array => [$t->code => "{$t->code} ({$t->rate_percent}%)"])->all();
    }

    private static function defaultLineAccountId(): ?int
    {
        $code = config('erp.accounts.grni');

        return Account::query()->where('code', $code)->value('id');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('number')->required()->maxLength(32)
                ->default(fn (): string => 'SINV-'.str_pad((string) (SupplierInvoice::query()->count() + 1), 4, '0', STR_PAD_LEFT))
                ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('company_id', app(CompanyContext::class)->currentId())),
            Forms\Components\Select::make('supplier_id')->relationship('supplier', 'name')->searchable()->preload()->required(),
            Forms\Components\Select::make('purchase_order_id')->label('Purchase order (optional)')
                ->relationship('purchaseOrder', 'po_number')->searchable()->preload(),
            Forms\Components\DatePicker::make('invoice_date')->default(now())->required(),
            Forms\Components\TextInput::make('supplier_ref')->label("Supplier's invoice no.")->maxLength(64),
            Forms\Components\TextInput::make('notes')->maxLength(255)->columnSpanFull(),
            Forms\Components\Repeater::make('lines')->relationship()->schema([
                Forms\Components\Select::make('account_id')->label('Account')
                    ->options(fn (): array => self::accountOptions())
                    ->default(fn (): ?int => self::defaultLineAccountId())
                    ->searchable()->required()
                    ->helperText('GRNI clears received goods; or pick an expense account'),
                Forms\Components\TextInput::make('amount')->label('Net amount')->numeric()->minValue(0.01)->required()
                    ->prefix(config('erp.currency.symbol')),
                Forms\Components\Select::make('tax_code')->label('VAT')->options(fn (): array => self::taxCodeOptions())
                    ->placeholder('No VAT'),
                Forms\Components\TextInput::make('description')->maxLength(255),
            ])->columns(4)->minItems(1)->addActionLabel('Add line')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('supplier.name')->searchable(),
                Tables\Columns\TextColumn::make('purchaseOrder.po_number')->label('PO')->placeholder('—'),
                Tables\Columns\TextColumn::make('invoice_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('lines_sum_amount')->label('Net')
                    ->sum('lines', 'amount')->money(config('erp.currency.code')),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (SupplierInvoiceStatus $state): string => $state->label())
                    ->color(fn (SupplierInvoiceStatus $state): string => $state->color()),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(SupplierInvoiceStatus::options()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('match')->label('3-way match')->icon('heroicon-o-shield-check')->color('info')
                    ->visible(fn (SupplierInvoice $record): bool => $record->purchase_order_id !== null)
                    ->modalHeading('3-way match — PO vs GRN vs Invoice')
                    ->modalSubmitAction(false)->modalCancelActionLabel('Close')
                    ->modalContent(fn (SupplierInvoice $record) => view('filament.purchasing.three-way-match', [
                        'match' => \App\Domain\Purchasing\ThreeWayMatch::for($record),
                    ])),
                Tables\Actions\EditAction::make()
                    ->visible(fn (SupplierInvoice $record): bool => $record->status === SupplierInvoiceStatus::Draft),
                Tables\Actions\Action::make('post')->icon('heroicon-o-check-badge')->color('success')
                    ->visible(fn (SupplierInvoice $record): bool => $record->status === SupplierInvoiceStatus::Draft)
                    ->requiresConfirmation()
                    ->modalDescription('Posting books the payable (with input VAT) and clears GRNI. It cannot be undone.')
                    ->action(function (SupplierInvoice $record): void {
                        try {
                            app(PostSupplierInvoice::class)->handle($record);
                            Notification::make()->title('Supplier invoice posted')->body('Payable recognised.')->success()->send();
                        } catch (SupplierInvoiceException|PostingException $e) {
                            Notification::make()->title('Cannot post invoice')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('print')->icon('heroicon-o-printer')->color('gray')
                    ->url(fn (SupplierInvoice $record): string => route('print.supplier-invoice', $record))->openUrlInNewTab(),
                Tables\Actions\Action::make('cancel')->icon('heroicon-o-x-mark')->color('danger')
                    ->visible(fn (SupplierInvoice $record): bool => $record->status === SupplierInvoiceStatus::Draft)
                    ->requiresConfirmation()
                    ->action(fn (SupplierInvoice $record) => $record->update(['status' => SupplierInvoiceStatus::Cancelled])),
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
            'index' => Pages\ListSupplierInvoices::route('/'),
            'create' => Pages\CreateSupplierInvoice::route('/create'),
            'view' => Pages\ViewSupplierInvoice::route('/{record}'),
            'edit' => Pages\EditSupplierInvoice::route('/{record}/edit'),
        ];
    }
}
