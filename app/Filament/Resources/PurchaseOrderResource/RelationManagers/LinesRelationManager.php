<?php

namespace App\Filament\Resources\PurchaseOrderResource\RelationManagers;

use App\Models\PurchaseOrderLine;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $title = 'Lines';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('product'))
            ->columns([
                Tables\Columns\TextColumn::make('product.name')->label('Product'),
                Tables\Columns\TextColumn::make('quantity_ordered')->label('Ordered'),
                Tables\Columns\TextColumn::make('quantity_received')->label('Received'),
                Tables\Columns\TextColumn::make('outstanding')->label('Outstanding')
                    ->state(fn (PurchaseOrderLine $record): string => (string) $record->outstanding()),
                Tables\Columns\TextColumn::make('unit_price')->money(config('erp.currency.code')),
                Tables\Columns\TextColumn::make('line_total')->label('Total')->money(config('erp.currency.code'))
                    ->state(fn (PurchaseOrderLine $record): string => (string) \Brick\Math\BigDecimal::of($record->quantity_ordered)->multipliedBy($record->unit_price)),
            ]);
    }
}
