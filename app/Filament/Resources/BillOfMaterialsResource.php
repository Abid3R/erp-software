<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BillOfMaterialsResource\Pages;
use App\Models\BillOfMaterials;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BillOfMaterialsResource extends Resource
{
    protected static ?string $model = BillOfMaterials::class;

    protected static ?string $slug = 'boms';

    protected static ?string $navigationIcon = 'heroicon-o-beaker';

    protected static ?string $navigationGroup = 'Manufacturing';

    protected static ?string $navigationLabel = 'Bills of Materials';

    protected static ?string $modelLabel = 'bill of materials';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('product_id')->label('Finished product')
                ->relationship('product', 'name')->searchable()->preload()->required(),
            Forms\Components\TextInput::make('name')->default('Default')->required()->maxLength(255),
            Forms\Components\TextInput::make('output_quantity')->numeric()->default(1)->minValue(0.0001)->required()
                ->helperText('Units produced per batch of the components below.'),
            Forms\Components\Toggle::make('is_active')->default(true),
            Forms\Components\Repeater::make('components')->relationship()->schema([
                Forms\Components\Select::make('component_product_id')->label('Component')
                    ->relationship('component', 'name')->searchable()->preload()->required(),
                Forms\Components\TextInput::make('quantity')->numeric()->minValue(0.0001)->required(),
            ])->columns(2)->addActionLabel('Add component')->defaultItems(1)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('product.name')->label('Finished product')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->label('Version'),
                Tables\Columns\TextColumn::make('output_quantity')->label('Output'),
                Tables\Columns\TextColumn::make('components_count')->counts('components')->label('Components'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->defaultSort('product_id');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBillOfMaterials::route('/'),
            'create' => Pages\CreateBillOfMaterials::route('/create'),
            'edit' => Pages\EditBillOfMaterials::route('/{record}/edit'),
        ];
    }
}
