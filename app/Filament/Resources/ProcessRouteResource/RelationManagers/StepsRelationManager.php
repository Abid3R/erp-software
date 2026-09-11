<?php

namespace App\Filament\Resources\ProcessRouteResource\RelationManagers;

use App\Models\ProcessType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class StepsRelationManager extends RelationManager
{
    protected static string $relationship = 'steps';

    protected static ?string $title = 'Route steps';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('sequence')->numeric()->minValue(1)->default(1)->required(),
            Forms\Components\Select::make('process_type_id')->label('Process')
                ->options(fn (): array => ProcessType::query()->where('is_active', true)->orderBy('sort')->pluck('name', 'id')->all())
                ->required()->native(false),
            Forms\Components\Select::make('output_product_id')->label('Output product')
                ->relationship('outputProduct', 'name')->searchable()->preload(),
            Forms\Components\TextInput::make('notes')->maxLength(255)->columnSpanFull(),
        ])->columns(3);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('sequence')
            ->columns([
                Tables\Columns\TextColumn::make('sequence')->label('#')->sortable(),
                Tables\Columns\TextColumn::make('processType.name')->label('Process')->badge(),
                Tables\Columns\TextColumn::make('outputProduct.name')->label('Output')->placeholder('—'),
                Tables\Columns\TextColumn::make('notes')->placeholder('—')->limit(40),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
