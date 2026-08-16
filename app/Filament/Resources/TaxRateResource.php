<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TaxRateResource\Pages;
use App\Models\TaxRate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Configurable tax/VAT rates (spec: Phase 4). Full CRUD — rates are reference data,
 * not posted transactions. Change a rate by ending the current one (effective_to)
 * and adding a new row; history is preserved for correctly dated documents.
 */
class TaxRateResource extends Resource
{
    protected static ?string $model = TaxRate::class;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationGroup = 'Accounts';

    protected static ?string $navigationLabel = 'Tax Rates';

    protected static ?int $navigationSort = 9;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')->required()->maxLength(32)
                ->helperText('Short key referenced by documents, e.g. VAT15'),
            Forms\Components\TextInput::make('name')->required()->maxLength(255),
            Forms\Components\TextInput::make('rate_percent')->label('Rate %')->numeric()
                ->minValue(0)->maxValue(100)->required()->suffix('%'),
            Forms\Components\Toggle::make('is_inclusive')->label('Prices include this tax'),
            Forms\Components\Select::make('input_account_id')->label('Input VAT account (purchases)')
                ->relationship('input', 'name')->searchable()->preload()
                ->helperText('Leave blank to use the company default'),
            Forms\Components\Select::make('output_account_id')->label('Output VAT account (sales)')
                ->relationship('output', 'name')->searchable()->preload()
                ->helperText('Leave blank to use the company default'),
            Forms\Components\DatePicker::make('effective_from')->default(now())->required(),
            Forms\Components\DatePicker::make('effective_to')->helperText('Blank = still in force'),
            Forms\Components\Toggle::make('is_active')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('rate_percent')->label('Rate %')->suffix('%')->sortable(),
                Tables\Columns\IconColumn::make('is_inclusive')->label('Inclusive')->boolean(),
                Tables\Columns\TextColumn::make('effective_from')->date()->sortable(),
                Tables\Columns\TextColumn::make('effective_to')->date()->placeholder('—'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('code');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTaxRates::route('/'),
            'create' => Pages\CreateTaxRate::route('/create'),
            'edit' => Pages\EditTaxRate::route('/{record}/edit'),
        ];
    }
}
