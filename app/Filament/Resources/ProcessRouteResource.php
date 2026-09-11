<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProcessRouteResource\Pages;
use App\Filament\Resources\ProcessRouteResource\RelationManagers\StepsRelationManager;
use App\Models\ProcessRoute;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Configurable process routes (routings) — a reusable, ordered sequence of process
 * types for producing a product. Production plans are generated from the product's
 * route when one exists, so production is not hard-coded to a single flow.
 */
class ProcessRouteResource extends Resource
{
    protected static ?string $model = ProcessRoute::class;

    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationGroup = 'Textile';

    protected static ?string $navigationLabel = 'Process Routes';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Route')->columns(2)->schema([
                Forms\Components\TextInput::make('name')->required()->maxLength(255)
                    ->placeholder('e.g. Knit → Dye → Finish'),
                Forms\Components\Select::make('product_id')->label('Produces (finished product)')
                    ->relationship('product', 'name')->searchable()->preload()
                    ->helperText('Plans for this product use this route.'),
                Forms\Components\Toggle::make('is_active')->default(true),
                Forms\Components\Textarea::make('notes')->rows(2)->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('product.name')->label('Produces')->placeholder('—')->searchable(),
                Tables\Columns\TextColumn::make('steps_count')->counts('steps')->label('Steps'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [
            StepsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProcessRoutes::route('/'),
            'create' => Pages\CreateProcessRoute::route('/create'),
            'edit' => Pages\EditProcessRoute::route('/{record}/edit'),
        ];
    }
}
