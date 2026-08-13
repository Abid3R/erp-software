<?php

namespace App\Filament\Resources\ManufacturingOrderResource\RelationManagers;

use App\Enums\InventoryTransactionType;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read-only manufacturing transaction history: the material issues and production
 * receipts posted to the inventory ledger for this order (spec: Manufacturing
 * transaction history). Traceability from the order to every stock movement.
 */
class InventoryMovementsRelationManager extends RelationManager
{
    protected static string $relationship = 'inventoryTransactions';

    protected static ?string $title = 'Inventory movements';

    protected static ?string $icon = 'heroicon-o-queue-list';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['product:id,sku,name', 'warehouse:id,name']))
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->dateTime()->label('Date'),
                Tables\Columns\TextColumn::make('type')->badge()
                    ->formatStateUsing(fn (InventoryTransactionType $state): string => $state->label()),
                Tables\Columns\TextColumn::make('product.sku')->label('Product'),
                Tables\Columns\TextColumn::make('warehouse.name')->label('Warehouse'),
                Tables\Columns\TextColumn::make('quantity')->label('Qty'),
                Tables\Columns\TextColumn::make('unit_cost')->money(config('erp.currency.code')),
                Tables\Columns\TextColumn::make('total_cost')->money(config('erp.currency.code')),
            ])
            ->defaultSort('id')
            ->paginated(false);
    }
}
