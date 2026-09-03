<?php

namespace App\Filament\Resources;

use App\Actions\Purchasing\PostGoodsReceipt;
use App\Enums\GoodsReceiptStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\QcStatus;
use App\Exceptions\GoodsReceiptException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\PostingException;
use App\Filament\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\GoodsReceiptResource\Pages;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Support\CompanyContext;
use App\Support\DocumentNumber;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class GoodsReceiptResource extends Resource
{
    protected static ?string $model = GoodsReceipt::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?string $navigationGroup = 'Purchasing';

    protected static ?string $navigationLabel = 'Goods Receipts (GRN)';

    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('number')->required()->maxLength(32)
                ->default(fn (): string => DocumentNumber::next('goods_receipt', 'GRN-', GoodsReceipt::query()->count()))
                ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('company_id', app(CompanyContext::class)->currentId())),
            Forms\Components\Select::make('purchase_order_id')->label('Purchase order')
                ->options(fn (): array => PurchaseOrder::query()
                    ->whereIn('status', [PurchaseOrderStatus::Approved->value, PurchaseOrderStatus::PartiallyReceived->value])
                    ->orderByDesc('id')->pluck('po_number', 'id')->all())
                ->searchable()->required()->live()
                ->afterStateUpdated(function ($state, Forms\Set $set): void {
                    $po = $state !== null ? PurchaseOrder::query()->with('lines')->find($state) : null;
                    if ($po === null) {
                        return;
                    }
                    $set('supplier_id', $po->supplier_id);
                    $set('warehouse_id', $po->warehouse_id);
                    $lines = [];
                    foreach ($po->lines as $line) {
                        $outstanding = $line->outstanding();
                        if ($outstanding->isLessThanOrEqualTo(0)) {
                            continue;
                        }
                        $lines[] = [
                            'purchase_order_line_id' => $line->getKey(),
                            'product_id' => $line->product_id,
                            'ordered_quantity' => (string) $outstanding,
                            'received_quantity' => (string) $outstanding,
                            'accepted_quantity' => (string) $outstanding,
                            'rejected_quantity' => '0',
                            'unit_cost' => (string) $line->unit_price,
                            'qc_status' => QcStatus::Passed->value,
                        ];
                    }
                    $set('lines', $lines);
                })
                ->disabledOn('edit'),
            Forms\Components\Select::make('supplier_id')->relationship('supplier', 'name')->searchable()->preload()->required(),
            Forms\Components\Select::make('warehouse_id')->relationship('warehouse', 'name')->searchable()->preload()->required(),
            Forms\Components\DatePicker::make('receipt_date')->default(now())->required(),
            Forms\Components\TextInput::make('supplier_challan_no')->label('Supplier challan no.')->maxLength(255),
            Forms\Components\TextInput::make('vehicle_no')->label('Vehicle no.')->maxLength(64),
            Forms\Components\TextInput::make('received_by')->maxLength(255),
            Forms\Components\TextInput::make('notes')->maxLength(255)->columnSpanFull(),
            Forms\Components\Repeater::make('lines')->relationship()->schema([
                Forms\Components\Hidden::make('purchase_order_line_id'),
                Forms\Components\Select::make('product_id')->label('Product')->relationship('product', 'name')->searchable()->preload()->required(),
                Forms\Components\TextInput::make('ordered_quantity')->numeric()->default(0)->readOnly(),
                Forms\Components\TextInput::make('received_quantity')->numeric()->minValue(0)->default(0)->required(),
                Forms\Components\TextInput::make('accepted_quantity')->numeric()->minValue(0)->default(0)->required()
                    ->helperText('Only accepted qty enters stock'),
                Forms\Components\TextInput::make('rejected_quantity')->numeric()->minValue(0)->default(0),
                Forms\Components\TextInput::make('unit_cost')->numeric()->minValue(0)->default(0)->required(),
                Forms\Components\Select::make('qc_status')->options(QcStatus::options())->default(QcStatus::Passed->value),
                Forms\Components\TextInput::make('batch_no')->maxLength(64),
                Forms\Components\DatePicker::make('expiry_date'),
                Forms\Components\TextInput::make('remarks')->maxLength(255),
            ])->columns(4)->minItems(1)->addActionLabel('Add line')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('purchaseOrder.po_number')->label('PO')->placeholder('—'),
                Tables\Columns\TextColumn::make('supplier.name')->searchable(),
                Tables\Columns\TextColumn::make('receipt_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('supplier_challan_no')->label('Challan')->placeholder('—'),
                Tables\Columns\TextColumn::make('lines_count')->counts('lines')->label('Lines'),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (GoodsReceiptStatus $state): string => $state->label())
                    ->color(fn (GoodsReceiptStatus $state): string => $state->color()),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(GoodsReceiptStatus::options()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (GoodsReceipt $record): bool => $record->status === GoodsReceiptStatus::Draft),
                Tables\Actions\Action::make('post')->icon('heroicon-o-check-badge')->color('success')
                    ->visible(fn (GoodsReceipt $record): bool => $record->status === GoodsReceiptStatus::Draft)
                    ->requiresConfirmation()
                    ->modalDescription('Posting receives the accepted quantities into stock (Dr Inventory / Cr GRNI) and updates the PO. It cannot be undone.')
                    ->action(function (GoodsReceipt $record): void {
                        try {
                            app(PostGoodsReceipt::class)->handle($record);
                            Notification::make()->title('Goods receipt posted')->body('Stock and PO updated.')->success()->send();
                        } catch (GoodsReceiptException|InsufficientStockException|PostingException $e) {
                            Notification::make()->title('Cannot post receipt')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('print')->icon('heroicon-o-printer')->color('gray')
                    ->url(fn (GoodsReceipt $record): string => route('print.goods-receipt', $record))->openUrlInNewTab(),
                Tables\Actions\Action::make('cancel')->icon('heroicon-o-x-mark')->color('danger')
                    ->visible(fn (GoodsReceipt $record): bool => $record->status === GoodsReceiptStatus::Draft)
                    ->requiresConfirmation()
                    ->action(fn (GoodsReceipt $record) => $record->update(['status' => GoodsReceiptStatus::Cancelled])),
            ])
            ->defaultSort('receipt_date', 'desc');
    }

    public static function getRelations(): array
    {
        return [DocumentsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGoodsReceipts::route('/'),
            'create' => Pages\CreateGoodsReceipt::route('/create'),
            'view' => Pages\ViewGoodsReceipt::route('/{record}'),
            'edit' => Pages\EditGoodsReceipt::route('/{record}/edit'),
        ];
    }
}
