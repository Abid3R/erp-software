<?php

namespace App\Filament\Resources;

use App\Actions\Sales\CancelDeliveryOrder;
use App\Actions\Sales\PostDeliveryOrder;
use App\Enums\DeliveryOrderStatus;
use App\Enums\SalesOrderStatus;
use App\Exceptions\DeliveryOrderException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\PostingException;
use App\Filament\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\DeliveryOrderResource\Pages;
use App\Models\DeliveryOrder;
use App\Models\SalesOrder;
use App\Support\CompanyContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DeliveryOrderResource extends Resource
{
    protected static ?string $model = DeliveryOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Sales';

    protected static ?string $navigationLabel = 'Delivery Orders';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Delivery information')->columns(2)->schema([
                Forms\Components\TextInput::make('number')->label('DO No.')->required()->maxLength(32)
                    ->default(fn (): string => 'DO-'.now()->format('Y').'-'
                        .str_pad((string) (DeliveryOrder::query()->whereYear('created_at', now()->year)->count() + 1), 5, '0', STR_PAD_LEFT))
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('company_id', app(CompanyContext::class)->currentId())),
                Forms\Components\DatePicker::make('delivery_date')->default(now())->required(),
                Forms\Components\Select::make('sales_order_id')->label('Sales order')
                    ->options(fn (): array => SalesOrder::query()
                        ->whereIn('status', [SalesOrderStatus::Confirmed->value, SalesOrderStatus::PartiallyDelivered->value])
                        ->orderByDesc('id')->pluck('so_number', 'id')->all())
                    ->searchable()->required()->live()
                    ->afterStateUpdated(function ($state, Forms\Set $set): void {
                        $so = $state !== null ? SalesOrder::query()->with('lines')->find($state) : null;
                        if ($so === null) {
                            return;
                        }
                        $set('customer_id', $so->customer_id);
                        $set('warehouse_id', $so->warehouse_id);
                        $lines = [];
                        foreach ($so->lines as $line) {
                            $outstanding = $line->outstanding();
                            if ($outstanding->isLessThanOrEqualTo(0)) {
                                continue;
                            }
                            $lines[] = [
                                'sales_order_line_id' => $line->getKey(),
                                'product_id' => $line->product_id,
                                'quantity' => (string) $outstanding,
                                'unit_price' => (string) $line->unit_price,
                            ];
                        }
                        $set('lines', $lines);
                    })
                    ->disabledOn('edit'),
                Forms\Components\Select::make('customer_id')->relationship('customer', 'name')->searchable()->preload()->required(),
                Forms\Components\Select::make('warehouse_id')->relationship('warehouse', 'name')->searchable()->preload()->required(),
                Forms\Components\Textarea::make('delivery_address')->rows(2)->maxLength(500),
            ]),
            Forms\Components\Section::make('Logistics')->columns(2)->schema([
                Forms\Components\TextInput::make('vehicle_no')->label('Vehicle no.')->maxLength(64),
                Forms\Components\TextInput::make('driver_name')->maxLength(128),
                Forms\Components\TextInput::make('driver_phone')->tel()->maxLength(32),
                Forms\Components\TextInput::make('transporter')->maxLength(128),
                Forms\Components\TextInput::make('customer_reference')->label('Customer reference')->maxLength(128),
                Forms\Components\TextInput::make('received_by')->maxLength(128),
            ])->collapsed(false),
            Forms\Components\Section::make('Items')->schema([
                Forms\Components\Repeater::make('lines')->relationship()->schema([
                    Forms\Components\Hidden::make('sales_order_line_id'),
                    Forms\Components\Select::make('product_id')->label('Product')
                        ->relationship('product', 'name')->searchable()->preload()->required(),
                    Forms\Components\TextInput::make('quantity')->numeric()->minValue(0.0001)->required(),
                    Forms\Components\TextInput::make('unit_price')->numeric()->minValue(0)
                        ->prefix(config('erp.currency.symbol')),
                    Forms\Components\TextInput::make('batch_no')->maxLength(64),
                    Forms\Components\TextInput::make('remarks')->maxLength(255),
                ])->columns(5)->minItems(1)->addActionLabel('Add line')->columnSpanFull(),
            ]),
            Forms\Components\TextInput::make('notes')->maxLength(255)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')->label('DO No.')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('salesOrder.so_number')->label('SO')->placeholder('—'),
                Tables\Columns\TextColumn::make('customer.name')->searchable(),
                Tables\Columns\TextColumn::make('warehouse.code')->label('Warehouse'),
                Tables\Columns\TextColumn::make('delivery_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('vehicle_no')->label('Vehicle')->placeholder('—'),
                Tables\Columns\TextColumn::make('lines_count')->counts('lines')->label('Lines'),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (DeliveryOrderStatus $state): string => $state->label())
                    ->color(fn (DeliveryOrderStatus $state): string => $state->color()),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(DeliveryOrderStatus::options()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (DeliveryOrder $record): bool => $record->status === DeliveryOrderStatus::Draft),
                Tables\Actions\Action::make('post')->label('Post & dispatch')->icon('heroicon-o-check-badge')->color('success')
                    ->visible(fn (DeliveryOrder $record): bool => $record->status === DeliveryOrderStatus::Draft)
                    ->requiresConfirmation()
                    ->modalDescription('Posting issues the goods from stock (Dr COGS / Cr Inventory) and advances the sales order. Invoice separately for AR/revenue. It can be reversed via Cancel.')
                    ->action(function (DeliveryOrder $record): void {
                        try {
                            app(PostDeliveryOrder::class)->handle($record);
                            Notification::make()->title('Delivery order posted')->body('Stock issued and sales order updated.')->success()->send();
                        } catch (DeliveryOrderException|InsufficientStockException|PostingException $e) {
                            Notification::make()->title('Cannot post delivery')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('cancel')->icon('heroicon-o-arrow-uturn-left')->color('danger')
                    ->visible(fn (DeliveryOrder $record): bool => $record->status->isPosted())
                    ->modalHeading('Cancel delivery order')
                    ->modalDescription('Reverses the stock movement and the COGS entry, and rolls back the sales order delivered quantity. Cannot be undone.')
                    ->form([
                        Forms\Components\Textarea::make('reason')->required()->minLength(3)->maxLength(255)
                            ->placeholder('e.g. goods returned, wrong address, customer refused'),
                    ])
                    ->action(function (DeliveryOrder $record, array $data): void {
                        try {
                            app(CancelDeliveryOrder::class)->handle($record, (string) $data['reason']);
                            Notification::make()->title('Delivery cancelled')->body('Stock returned and SO delivered qty rolled back.')->success()->send();
                        } catch (DeliveryOrderException|PostingException $e) {
                            Notification::make()->title('Cannot cancel')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('print')->icon('heroicon-o-printer')->color('gray')
                    ->url(fn (DeliveryOrder $record): string => route('print.delivery-order', $record))->openUrlInNewTab(),
            ])
            ->defaultSort('delivery_date', 'desc');
    }

    public static function getRelations(): array
    {
        return [DocumentsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDeliveryOrders::route('/'),
            'create' => Pages\CreateDeliveryOrder::route('/create'),
            'view' => Pages\ViewDeliveryOrder::route('/{record}'),
            'edit' => Pages\EditDeliveryOrder::route('/{record}/edit'),
        ];
    }
}
