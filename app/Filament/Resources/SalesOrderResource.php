<?php

namespace App\Filament\Resources;

use App\Actions\Sales\FulfillSalesOrder;
use App\Enums\SalesOrderStatus;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\PostingException;
use App\Exceptions\SalesOrderException;
use App\Filament\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\DeliveryOrderResource;
use App\Filament\Resources\SalesOrderResource\Pages;
use App\Filament\Resources\SalesOrderResource\RelationManagers\LinesRelationManager;
use App\Models\SalesOrder;
use App\Support\CompanyContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SalesOrderResource extends Resource
{
    protected static ?string $model = SalesOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationGroup = 'Sales';

    protected static ?string $navigationLabel = 'Sales Orders';

    protected static ?int $navigationSort = 3;

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::query()->whereIn('status', ['confirmed', 'partially_delivered'])->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('lines');
    }

    /** Whether the active company requires the Delivery Order workflow. */
    private static function requiresDeliveryOrder(): bool
    {
        return (bool) (app(CompanyContext::class)->current()?->require_delivery_order ?? false);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('so_number')->label('SO #')->required()->maxLength(32)
                ->default(fn (): string => 'SO-'.str_pad((string) (SalesOrder::query()->count() + 1), 4, '0', STR_PAD_LEFT))
                ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('company_id', app(CompanyContext::class)->currentId())),
            Forms\Components\Select::make('customer_id')->relationship('customer', 'name')->searchable()->preload()->required(),
            Forms\Components\Select::make('warehouse_id')->relationship('warehouse', 'name')->searchable()->preload()->required(),
            Forms\Components\DatePicker::make('order_date')->default(now())->required(),
            Forms\Components\DatePicker::make('delivery_date'),
            Forms\Components\TextInput::make('notes')->maxLength(255)->columnSpanFull(),
            Forms\Components\Repeater::make('lines')->relationship()->schema([
                Forms\Components\Select::make('product_id')->label('Product')->relationship('product', 'name')->searchable()->preload()->required(),
                Forms\Components\TextInput::make('quantity_ordered')->numeric()->minValue(0.0001)->required(),
                Forms\Components\TextInput::make('unit_price')->numeric()->minValue(0)->required()->prefix(config('erp.currency.symbol')),
            ])->columns(3)->minItems(1)->addActionLabel('Add line')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('so_number')->label('SO #')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('customer.name')->searchable(),
                Tables\Columns\TextColumn::make('order_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (SalesOrderStatus $state): string => $state->label())
                    ->color(fn (SalesOrderStatus $state): string => $state->color()),
                Tables\Columns\TextColumn::make('total')->money(config('erp.currency.code'))
                    ->state(fn (SalesOrder $record): string => (string) $record->total()),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(SalesOrderStatus::options()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (SalesOrder $record): bool => $record->status === SalesOrderStatus::Draft),
                Tables\Actions\Action::make('confirm')->icon('heroicon-o-check')->color('info')
                    ->visible(fn (SalesOrder $record): bool => $record->status === SalesOrderStatus::Draft)
                    ->requiresConfirmation()
                    ->action(function (SalesOrder $record): void {
                        $record->update(['status' => SalesOrderStatus::Confirmed]);
                        Notification::make()->title('Sales order confirmed')->success()->send();
                    }),
                // Create a Delivery Order when the company requires the DO workflow.
                Tables\Actions\Action::make('createDo')->label('Create DO')->icon('heroicon-o-truck')->color('success')
                    ->visible(fn (SalesOrder $record): bool => $record->status->isDeliverable() && self::requiresDeliveryOrder())
                    ->url(fn (SalesOrder $record): string => DeliveryOrderResource::getUrl('create', ['sales_order_id' => $record->getKey()])),
                // Direct deliver (existing flow) — only when the company does NOT require a DO.
                Tables\Actions\Action::make('deliver')->icon('heroicon-o-truck')->color('success')
                    ->visible(fn (SalesOrder $record): bool => $record->status->isDeliverable() && ! self::requiresDeliveryOrder())
                    ->form(fn (SalesOrder $record): array => [
                        Forms\Components\DatePicker::make('date')->default(now())->required(),
                        ...$record->lines
                            ->filter(fn ($line): bool => $line->outstanding()->isGreaterThan(0))
                            ->map(fn ($line) => Forms\Components\TextInput::make('deliver_'.$line->getKey())
                                ->label(((string) data_get($line, 'product.name', 'Item')).' (outstanding '.$line->outstanding().')')
                                ->numeric()->minValue(0)->default((string) $line->outstanding()))
                            ->values()->all(),
                    ])
                    ->action(function (SalesOrder $record, array $data): void {
                        $deliveries = [];
                        foreach ($record->lines as $line) {
                            $value = $data['deliver_'.$line->getKey()] ?? null;
                            if ($value !== null && (float) $value > 0) {
                                $deliveries[$line->getKey()] = $value;
                            }
                        }
                        try {
                            app(FulfillSalesOrder::class)->handle($record, $deliveries, (string) $data['date']);
                            Notification::make()->title('Delivered & invoiced')->body('Stock, revenue and receivable updated.')->success()->send();
                        } catch (SalesOrderException|InsufficientStockException|PostingException $e) {
                            Notification::make()->title('Cannot deliver')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('print')->icon('heroicon-o-printer')
                    ->url(fn (SalesOrder $record): string => route('print.sales-order', $record))->openUrlInNewTab(),
                Tables\Actions\Action::make('cancel')->icon('heroicon-o-x-mark')->color('danger')
                    ->visible(fn (SalesOrder $record): bool => in_array($record->status, [SalesOrderStatus::Draft, SalesOrderStatus::Confirmed], true))
                    ->requiresConfirmation()
                    ->action(fn (SalesOrder $record) => $record->update(['status' => SalesOrderStatus::Cancelled])),
            ])
            ->defaultSort('order_date', 'desc');
    }

    public static function getRelations(): array
    {
        return [LinesRelationManager::class, DocumentsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSalesOrders::route('/'),
            'create' => Pages\CreateSalesOrder::route('/create'),
            'view' => Pages\ViewSalesOrder::route('/{record}'),
            'edit' => Pages\EditSalesOrder::route('/{record}/edit'),
        ];
    }
}
