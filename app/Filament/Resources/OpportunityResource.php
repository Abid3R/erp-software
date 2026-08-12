<?php

namespace App\Filament\Resources;

use App\Enums\OpportunityStage;
use App\Filament\Resources\OpportunityResource\Pages;
use App\Models\Opportunity;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OpportunityResource extends Resource
{
    protected static ?string $model = Opportunity::class;

    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static ?string $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('Opportunity')->required()->maxLength(255),
            Forms\Components\Select::make('customer_id')->relationship('customer', 'name')->searchable()->preload(),
            Forms\Components\Select::make('stage')->options(OpportunityStage::options())->default('prospecting')->required()
                ->live()
                ->afterStateUpdated(function ($state, Forms\Set $set): void {
                    $stage = OpportunityStage::tryFrom((string) $state);
                    if ($stage !== null) {
                        $set('probability', $stage->defaultProbability());
                    }
                }),
            Forms\Components\TextInput::make('expected_value')->numeric()->minValue(0)->default(0)->prefix(config('erp.currency.symbol')),
            Forms\Components\TextInput::make('probability')->numeric()->minValue(0)->maxValue(100)->default(10)->suffix('%'),
            Forms\Components\DatePicker::make('expected_close_date'),
            Forms\Components\Select::make('owner_id')->relationship('owner', 'name')->searchable()->preload()->label('Owner'),
            Forms\Components\Textarea::make('notes')->maxLength(255)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('customer.name')->placeholder('—'),
                Tables\Columns\TextColumn::make('stage')->badge()
                    ->formatStateUsing(fn (OpportunityStage $state): string => $state->label())
                    ->color(fn (OpportunityStage $state): string => $state->color()),
                Tables\Columns\TextColumn::make('expected_value')->money(config('erp.currency.code'))->sortable(),
                Tables\Columns\TextColumn::make('probability')->suffix('%'),
                Tables\Columns\TextColumn::make('weighted')->label('Weighted')->money(config('erp.currency.code'))
                    ->state(fn (Opportunity $record): string => (string) $record->weightedValue()),
                Tables\Columns\TextColumn::make('expected_close_date')->date()->placeholder('—')->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('stage')->options(OpportunityStage::options()),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->defaultSort('expected_close_date');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOpportunities::route('/'),
            'create' => Pages\CreateOpportunity::route('/create'),
            'edit' => Pages\EditOpportunity::route('/{record}/edit'),
        ];
    }
}
