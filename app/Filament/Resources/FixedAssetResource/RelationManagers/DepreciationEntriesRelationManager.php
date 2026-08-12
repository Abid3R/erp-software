<?php

namespace App\Filament\Resources\FixedAssetResource\RelationManagers;

use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class DepreciationEntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'depreciationEntries';

    protected static ?string $title = 'Depreciation history';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('period')->sortable(),
                Tables\Columns\TextColumn::make('amount')->money(config('erp.currency.code')),
                Tables\Columns\TextColumn::make('journal_id')->label('Journal #')->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->toggleable(),
            ])
            ->defaultSort('period', 'desc');
    }
}
