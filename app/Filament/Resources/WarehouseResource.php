<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WarehouseResource\Pages;
use App\Models\Warehouse;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Warehouse master data. Warehouse types (Main, Factory, Raw Material Store,
 * Retail Store, Transit, etc.) are just distinct records — the ERP treats them
 * uniformly so operators can add new stores without code changes.
 */
class WarehouseResource extends Resource
{
    protected static ?string $model = Warehouse::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?string $navigationLabel = 'Warehouses';

    protected static ?int $navigationSort = 20;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required()->maxLength(255)
                ->helperText('e.g. Main Warehouse, Factory Store, Raw Material Store, Retail Store'),
            Forms\Components\TextInput::make('code')->required()->maxLength(32)
                ->helperText('Short key used on documents, e.g. MAIN, FACT, RM, FG, RET'),
            Forms\Components\Select::make('branch_id')->label('Branch')
                ->relationship('branch', 'name')->searchable()->preload(),
            Forms\Components\TextInput::make('contact_person')->maxLength(255),
            Forms\Components\TextInput::make('phone')->tel()->maxLength(64),
            Forms\Components\Textarea::make('address')->rows(2)->columnSpanFull(),
            Forms\Components\Select::make('allow_negative_stock')
                ->label('Negative-stock policy')
                ->options([
                    '' => 'Inherit company default',
                    '1' => 'Allow negative stock',
                    '0' => 'Block negative stock',
                ])
                ->native(false)
                ->helperText('Leave blank to inherit the company setting.'),
            Forms\Components\Toggle::make('is_active')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('branch.name')->label('Branch')->placeholder('—')->sortable(),
                Tables\Columns\TextColumn::make('contact_person')->label('Contact')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('phone')->placeholder('—')->toggleable(),
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
            'index' => Pages\ListWarehouses::route('/'),
            'create' => Pages\CreateWarehouse::route('/create'),
            'edit' => Pages\EditWarehouse::route('/{record}/edit'),
        ];
    }
}
