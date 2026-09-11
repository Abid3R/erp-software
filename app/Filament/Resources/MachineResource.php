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

    protected static ?string $navigationGroup = 'Textile';

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
            ])->native(false)->placeholder('General')->live(),
            Forms\Components\TextInput::make('hourly_cost')->numeric()->default(0)->minValue(0)
                ->prefix(config('erp.currency.symbol'))->helperText('Used for machine-cost absorption.'),
            Forms\Components\TextInput::make('capacity_per_hour')->numeric()->minValue(0)
                ->helperText('Optional throughput (units/hour).'),
            Forms\Components\Toggle::make('is_active')->default(true),
            // Knitting-machine attributes (circular knitting): shown for knitting machines.
            Forms\Components\Fieldset::make('Knitting specification')
                ->visible(fn (Forms\Get $get): bool => $get('type') === 'knitting')
                ->schema([
                    Forms\Components\TextInput::make('diameter')->label('Cylinder dia (inch)')->numeric()->minValue(0)
                        ->helperText('e.g. 30, 34, 38'),
                    Forms\Components\TextInput::make('gauge')->label('Gauge (needles/inch)')->numeric()->minValue(0)
                        ->helperText('e.g. 24, 28'),
                    Forms\Components\TextInput::make('feeder_count')->label('Feeders')->numeric()->minValue(0),
                    Forms\Components\TextInput::make('needle_count')->label('Needles')->numeric()->minValue(0),
                ])->columns(2)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('type')->badge()->placeholder('general')->sortable(),
                Tables\Columns\TextColumn::make('diameter')->label('Dia"')->placeholder('—')
                    ->formatStateUsing(fn ($state): string => $state ? rtrim(rtrim((string) $state, '0'), '.').'"' : '—'),
                Tables\Columns\TextColumn::make('gauge')->label('GG')->placeholder('—'),
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
