<?php

namespace App\Filament\Resources;

use App\Enums\FixedAssetStatus;
use App\Filament\Resources\FixedAssetResource\Pages;
use App\Filament\Resources\FixedAssetResource\RelationManagers\DepreciationEntriesRelationManager;
use App\Models\FixedAsset;
use App\Support\CompanyContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class FixedAssetResource extends Resource
{
    protected static ?string $model = FixedAsset::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $navigationGroup = 'Accounts';

    protected static ?string $navigationLabel = 'Fixed Assets';

    protected static ?int $navigationSort = 11;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('asset_code')->required()->maxLength(32)
                ->default(fn (): string => 'FA-'.str_pad((string) (FixedAsset::query()->count() + 1), 4, '0', STR_PAD_LEFT))
                ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('company_id', app(CompanyContext::class)->currentId())),
            Forms\Components\TextInput::make('name')->required()->maxLength(255),
            Forms\Components\TextInput::make('category')->maxLength(255),
            Forms\Components\DatePicker::make('acquisition_date')->required()->default(now()),
            Forms\Components\TextInput::make('acquisition_cost')->numeric()->minValue(0)->required()->prefix(config('erp.currency.symbol')),
            Forms\Components\TextInput::make('salvage_value')->numeric()->minValue(0)->default(0)->prefix(config('erp.currency.symbol')),
            Forms\Components\TextInput::make('useful_life_months')->numeric()->minValue(1)->required()->suffix('months'),
            Forms\Components\TextInput::make('notes')->maxLength(255)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('asset_code')->label('Code')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('acquisition_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('acquisition_cost')->money(config('erp.currency.code')),
                Tables\Columns\TextColumn::make('accumulated_depreciation')->label('Accum. dep.')->money(config('erp.currency.code')),
                Tables\Columns\TextColumn::make('nbv')->label('Net book value')->money(config('erp.currency.code'))
                    ->state(fn (FixedAsset $record): string => (string) $record->netBookValue()),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (FixedAssetStatus $state): string => $state->label())
                    ->color(fn (FixedAssetStatus $state): string => $state->color()),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(FixedAssetStatus::options()),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->defaultSort('asset_code');
    }

    public static function getRelations(): array
    {
        return [DepreciationEntriesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFixedAssets::route('/'),
            'create' => Pages\CreateFixedAsset::route('/create'),
            'edit' => Pages\EditFixedAsset::route('/{record}/edit'),
        ];
    }
}
