<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupplierPriceResource\Pages;
use App\Models\SupplierPrice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SupplierPriceResource extends Resource
{
    protected static ?string $model = SupplierPrice::class;

    protected static ?string $slug = 'buying-prices';

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Purchasing';

    protected static ?string $navigationLabel = 'Buying Prices';

    protected static ?string $modelLabel = 'buying price';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('supplier_id')->relationship('supplier', 'name')->searchable()->preload()->required(),
            Forms\Components\Select::make('product_id')->relationship('product', 'name')->searchable()->preload()->required(),
            Forms\Components\TextInput::make('unit_price')->numeric()->minValue(0)->required()->prefix(config('erp.currency.symbol')),
            Forms\Components\Toggle::make('is_active')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('supplier.name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('product.name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('unit_price')->money(config('erp.currency.code')),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('supplier')->relationship('supplier', 'name'),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->defaultSort('supplier_id');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSupplierPrices::route('/'),
            'create' => Pages\CreateSupplierPrice::route('/create'),
            'edit' => Pages\EditSupplierPrice::route('/{record}/edit'),
        ];
    }
}
