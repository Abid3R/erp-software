<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MachineResource\Pages;
use App\Models\Machine;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Machines / work centres for textile production (knitting, dyeing, finishing).
 * The hourly cost feeds machine-cost absorption in production costing.
 */
class MachineResource extends Resource
{
    protected static ?string $model = Machine::class;

    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?string $navigationGroup = 'Manufacturing';

    protected static ?string $navigationLabel = 'Machines';

    protected static ?int $navigationSort = 20;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')->required()->maxLength(32)
                ->helperText('Short code, e.g. KNIT-01, DYE-02'),
            Forms\Components\TextInput::make('name')->required()->maxLength(255),
            Forms\Components\Select::make('type')->options([
                'knitting' => 'Knitting',
                'dyeing' => 'Dyeing',
                'finishing' => 'Finishing',
                'general' => 'General',
            ])->native(false)->placeholder('General'),
            Forms\Components\TextInput::make('hourly_cost')->numeric()->default(0)->minValue(0)
                ->prefix(config('erp.currency.symbol'))->helperText('Used for machine-cost absorption.'),
            Forms\Components\TextInput::make('capacity_per_hour')->numeric()->minValue(0)
                ->helperText('Optional throughput (units/hour).'),
            Forms\Components\Toggle::make('is_active')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('type')->badge()->placeholder('general')->sortable(),
                Tables\Columns\TextColumn::make('hourly_cost')->money(config('erp.currency.code'))->sortable(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')->options([
                    'knitting' => 'Knitting', 'dyeing' => 'Dyeing', 'finishing' => 'Finishing', 'general' => 'General',
                ]),
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
            'index' => Pages\ManageMachines::route('/'),
        ];
    }
}
