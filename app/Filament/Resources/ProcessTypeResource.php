<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProcessTypeResource\Pages;
use App\Models\ProcessType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Configurable textile process types (Knitting, Dyeing, Finishing, and any added
 * later). The behaviour flags drive the shared process engine, so a new process
 * can be introduced here without code changes.
 */
class ProcessTypeResource extends Resource
{
    protected static ?string $model = ProcessType::class;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-vertical';

    protected static ?string $navigationGroup = 'Manufacturing';

    protected static ?string $navigationLabel = 'Process Types';

    protected static ?int $navigationSort = 21;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')->required()->maxLength(32)
                ->helperText('Short code, e.g. KNIT, DYE, FINISH'),
            Forms\Components\TextInput::make('name')->required()->maxLength(255),
            Forms\Components\TextInput::make('sort')->numeric()->default(0),
            Forms\Components\Toggle::make('is_active')->default(true),
            Forms\Components\Fieldset::make('Behaviour')->schema([
                Forms\Components\Toggle::make('consumes_material')->default(true)
                    ->helperText('Issues input materials from stock.'),
                Forms\Components\Toggle::make('produces_material')->default(true)
                    ->helperText('Receives the produced output into stock.'),
                Forms\Components\Toggle::make('requires_lab_dip')->default(false)
                    ->helperText('Requires an approved lab dip (e.g. dyeing).'),
                Forms\Components\Toggle::make('requires_qc')->default(false)
                    ->helperText('Requires a QC pass before completion.'),
            ])->columns(2),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\IconColumn::make('consumes_material')->boolean()->label('Consumes'),
                Tables\Columns\IconColumn::make('produces_material')->boolean()->label('Produces'),
                Tables\Columns\IconColumn::make('requires_lab_dip')->boolean()->label('Lab dip'),
                Tables\Columns\IconColumn::make('requires_qc')->boolean()->label('QC'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->defaultSort('sort');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageProcessTypes::route('/'),
        ];
    }
}
