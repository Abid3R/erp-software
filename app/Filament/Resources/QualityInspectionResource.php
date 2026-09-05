<?php

namespace App\Filament\Resources;

use App\Enums\QualityStatus;
use App\Filament\Resources\QualityInspectionResource\Pages;
use App\Models\QualityInspection;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Quality inspection register. Inspections are created by the QC action on a
 * process order (which also removes any rejected quantity from stock), so this
 * screen is read-only — a record and report of QC outcomes.
 */
class QualityInspectionResource extends Resource
{
    protected static ?string $model = QualityInspection::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Manufacturing';

    protected static ?string $navigationLabel = 'Quality Inspections';

    protected static ?int $navigationSort = 10;

    public static function canCreate(): bool
    {
        return false; // created via the QC action on a process order
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['inspectable', 'product', 'inspectedBy']))
            ->columns([
                Tables\Columns\TextColumn::make('reference')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('inspectable.reference')->label('Order')->placeholder('—'),
                Tables\Columns\TextColumn::make('product.name')->label('Product')->placeholder('—')->searchable(),
                Tables\Columns\TextColumn::make('inspected_quantity')->label('Inspected')->alignRight(),
                Tables\Columns\TextColumn::make('passed_quantity')->label('Passed')->alignRight(),
                Tables\Columns\TextColumn::make('rejected_quantity')->label('Rejected')->alignRight(),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (QualityStatus $state): string => $state->label())
                    ->color(fn (QualityStatus $state): string => $state->color()),
                Tables\Columns\TextColumn::make('inspected_at')->dateTime()->sortable()->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(QualityStatus::options()),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQualityInspections::route('/'),
        ];
    }
}
