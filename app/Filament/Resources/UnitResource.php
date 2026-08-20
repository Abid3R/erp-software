<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UnitResource\Pages;
use App\Models\Unit;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Units of measurement. Base units have factor = 1 and no parent. Derived units
 * (Carton, Dozen, etc.) reference a base unit and store how many base units they
 * represent. Existing products keep their current unit — nothing is auto-converted.
 */
class UnitResource extends Resource
{
    protected static ?string $model = Unit::class;

    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?string $navigationLabel = 'Units';

    protected static ?int $navigationSort = 21;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required()->maxLength(255)
                ->helperText('e.g. Kilogram, Meter, Carton, Dozen'),
            Forms\Components\TextInput::make('code')->required()->maxLength(32)
                ->helperText('Short code, e.g. KG, M, CTN, DOZ'),
            Forms\Components\Select::make('base_unit_id')->label('Base unit')
                ->relationship('baseUnit', 'name')->searchable()->preload()
                ->helperText('Leave blank for a base unit (factor = 1).'),
            Forms\Components\TextInput::make('factor')->numeric()->minValue(0.000001)->default(1)->required()
                ->helperText('How many base units this unit represents (e.g. Dozen = 12).'),
            Forms\Components\Toggle::make('is_active')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('baseUnit.code')->label('Base')->placeholder('— (base)')->sortable(),
                Tables\Columns\TextColumn::make('factor')->sortable(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->defaultSort('code');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUnits::route('/'),
            'create' => Pages\CreateUnit::route('/create'),
            'edit' => Pages\EditUnit::route('/{record}/edit'),
        ];
    }
}
