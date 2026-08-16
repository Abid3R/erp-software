<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockResource\Pages;
use App\Models\StockBalance;
use Brick\Math\BigDecimal;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read-only stock levels (spec #31). Quantities and moving-average cost come from
 * the inventory ledger; not editable here (movements happen through documents).
 * Low-stock items are flagged and counted in the nav badge (spec #39 low-stock).
 */
class StockResource extends Resource
{
    protected static ?string $model = StockBalance::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Stock Levels';

    /** Correlated subquery: on-hand at or below the product's reorder level. */
    private const LOW_STOCK = 'stock_balances.quantity_on_hand <= (select reorder_level from products where products.id = stock_balances.product_id and reorder_level is not null)';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::query()->whereRaw(self::LOW_STOCK)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['product', 'warehouse']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('product.sku')->label('SKU')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('product.name')->label('Product')->searchable(),
                Tables\Columns\TextColumn::make('warehouse.code')->label('Warehouse')->sortable(),
                Tables\Columns\TextColumn::make('quantity_on_hand')->label('On hand')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('average_cost')->label('Avg cost')->money(config('erp.currency.code')),
                Tables\Columns\TextColumn::make('value')->label('Value')
                    ->money(config('erp.currency.code'))
                    ->state(fn (StockBalance $record): string => (string) BigDecimal::of($record->quantity_on_hand)
                        ->multipliedBy($record->average_cost)->toScale(2)),
                Tables\Columns\IconColumn::make('low_stock')->label('Low')
                    ->boolean()
                    ->state(fn (StockBalance $record): bool => $record->product->reorder_level !== null
                        && BigDecimal::of($record->quantity_on_hand)->isLessThanOrEqualTo($record->product->reorder_level)),
            ])
            ->filters([
                Tables\Filters\Filter::make('low_stock')
                    ->label('Low stock only')
                    ->query(fn (Builder $query): Builder => $query->whereRaw(self::LOW_STOCK)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStock::route('/'),
        ];
    }
}
