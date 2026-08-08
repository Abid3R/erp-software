<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use App\Support\CompanyContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Catalog';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Product')->columns(2)->schema([
                Forms\Components\TextInput::make('name')->required()->maxLength(255),
                Forms\Components\TextInput::make('sku')
                    ->label('SKU')
                    ->required()
                    ->maxLength(255)
                    // Uniqueness is per-company; company_id is stamped server-side.
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where(
                        'company_id',
                        app(CompanyContext::class)->currentId(),
                    )),
                Forms\Components\TextInput::make('barcode')->maxLength(255),
                Forms\Components\Select::make('unit_id')
                    ->label('Stock unit')
                    ->relationship('unit', 'name')
                    ->required()
                    ->preload(),
                Forms\Components\Select::make('category_id')
                    ->relationship('category', 'name')
                    ->searchable()->preload(),
                Forms\Components\Select::make('brand_id')
                    ->relationship('brand', 'name')
                    ->searchable()->preload(),
            ]),
            Forms\Components\Section::make('Pricing & stock')->columns(2)->schema([
                Forms\Components\TextInput::make('cost_price')
                    ->numeric()->default(0)->minValue(0)
                    ->prefix(config('erp.currency.symbol')),
                Forms\Components\TextInput::make('selling_price')
                    ->numeric()->default(0)->minValue(0)
                    ->prefix(config('erp.currency.symbol')),
                Forms\Components\TextInput::make('reorder_level')->numeric()->minValue(0),
                Forms\Components\Toggle::make('is_active')->default(true),
                Forms\Components\Toggle::make('tracks_batch')->label('Track batches'),
                Forms\Components\Toggle::make('tracks_serial')->label('Track serials'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sku')->label('SKU')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('category.name')->label('Category')->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('unit.code')->label('Unit'),
                Tables\Columns\TextColumn::make('selling_price')
                    ->money(config('erp.currency.code'))->sortable(),
                Tables\Columns\IconColumn::make('is_active')->boolean()->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
