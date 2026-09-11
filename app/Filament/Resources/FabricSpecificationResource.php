<?php

namespace App\Filament\Resources;

use App\Enums\ConsumptionBasis;
use App\Filament\Resources\FabricSpecificationResource\Pages;
use App\Models\ProductSpecification;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Fabric/knitting specification master — one standard spec per product. Defining it
 * here means selecting the product on a knitting process order or sub-contract
 * auto-fills the fabric fields, and the spec prints as an operator sheet for the
 * machine.
 */
class FabricSpecificationResource extends Resource
{
    protected static ?string $model = ProductSpecification::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Textile';

    protected static ?string $navigationLabel = 'Fabric Specifications';

    protected static ?string $modelLabel = 'fabric specification';

    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Product')->schema([
                Forms\Components\Select::make('product_id')->label('Product (fabric)')
                    ->relationship('product', 'name')->searchable()->preload()->required()
                    ->unique(ignoreRecord: true)
                    ->helperText('The fabric this specification describes.'),
            ]),
            Forms\Components\Section::make('Fabric')->columns(3)->schema([
                Forms\Components\TextInput::make('fabric_composition')->label('Composition')->maxLength(255)
                    ->placeholder('e.g. 80% Cotton 20% Polyester')->columnSpan(2),
                Forms\Components\TextInput::make('gsm')->label('GSM')->maxLength(32)->placeholder('e.g. 280/290'),
                Forms\Components\TextInput::make('fabric_width')->label('Width / Dia')->maxLength(64)
                    ->placeholder('e.g. 72 Inch Open'),
                Forms\Components\TextInput::make('colour')->maxLength(255),
                Forms\Components\TextInput::make('colour_ref')->label('Colour ref')->maxLength(255),
            ]),
            Forms\Components\Section::make('Knitting parameters')->columns(3)->schema([
                Forms\Components\TextInput::make('machine_diameter')->label('Machine dia')->placeholder('e.g. 30'),
                Forms\Components\TextInput::make('gauge')->placeholder('e.g. 24'),
                Forms\Components\TextInput::make('stitch_length')->label('Stitch length')->placeholder('e.g. 4.1+5.0+0.5'),
                Forms\Components\TextInput::make('yarn_count')->label('Yarn count')->placeholder('e.g. 30s'),
                Forms\Components\TextInput::make('fabric_type')->label('Fabric type')->placeholder('e.g. Single Jersey'),
                Forms\Components\TextInput::make('quality')->placeholder('e.g. Combed'),
                Forms\Components\Textarea::make('notes')->rows(2)->columnSpanFull(),
            ]),
            Forms\Components\Section::make('Material consumption (per unit of output)')
                ->description('How much of each yarn/material is needed to make 1 unit of this fabric. An order multiplies this by its quantity and adds wastage.')
                ->schema([
                    Forms\Components\Repeater::make('consumptions')->relationship()
                        ->hiddenLabel()
                        ->schema([
                            Forms\Components\Select::make('product_id')->label('Material (yarn)')
                                ->relationship('product', 'name')->searchable()->preload()->required(),
                            Forms\Components\Hidden::make('basis')->default(ConsumptionBasis::PerUnit->value),
                            Forms\Components\TextInput::make('rate')->label('Qty per unit')->numeric()->minValue(0)->required()
                                ->helperText('e.g. 1.0 kg yarn per kg fabric'),
                            Forms\Components\TextInput::make('wastage_percent')->label('Wastage %')->numeric()->minValue(0)->default(0)
                                ->helperText('Knitting process loss, e.g. 5'),
                        ])->columns(3)->addActionLabel('Add material')->defaultItems(1)->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('product.name')->label('Product')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('fabric_composition')->label('Composition')->placeholder('—')->wrap(),
                Tables\Columns\TextColumn::make('gsm')->label('GSM')->placeholder('—'),
                Tables\Columns\TextColumn::make('machine_diameter')->label('Dia')->placeholder('—'),
                Tables\Columns\TextColumn::make('gauge')->placeholder('—'),
                Tables\Columns\TextColumn::make('stitch_length')->label('Stitch length')->placeholder('—'),
            ])
            ->actions([
                Tables\Actions\Action::make('print')->label('Spec sheet')
                    ->icon('heroicon-o-printer')->color('gray')
                    ->url(fn (ProductSpecification $record): string => route('print.fabric-specification', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('product_id');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageFabricSpecifications::route('/'),
        ];
    }
}
