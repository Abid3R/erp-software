<?php

namespace App\Filament\Resources\ProductionPlanResource\RelationManagers;

use App\Enums\ProcessOrderStatus;
use App\Filament\Resources\KnittingSubcontractResource;
use App\Filament\Resources\ProcessOrderResource;
use App\Models\ProcessOrder;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * The actual process orders spawned from this plan's stages, shown inside the plan
 * and grouped by process (Knitting / Dyeing / Finishing) so the whole order's
 * production is visible and navigable in one place. Read-only here — orders are
 * created from stages and run on the Process Orders screen (via the Open link).
 */
class ProcessOrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'processOrders';

    protected static ?string $title = 'Process orders';

    protected static ?string $icon = 'heroicon-o-cog-6-tooth';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id')
            ->groups([
                Tables\Grouping\Group::make('processType.name')->label('Process')->collapsible(),
            ])
            ->defaultGroup('processType.name')
            ->paginated([25, 50, 100, 'all'])
            ->defaultPaginationPageOption(50)
            ->columns([
                Tables\Columns\TextColumn::make('reference')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('notes')->label('Item')->placeholder('—')->wrap()->limit(60),
                Tables\Columns\TextColumn::make('outputProduct.name')->label('Output')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('planned_quantity')->label('Planned'),
                Tables\Columns\TextColumn::make('produced_quantity')->label('Produced')->placeholder('—'),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (ProcessOrderStatus $state): string => $state->label())
                    ->color(fn (ProcessOrderStatus $state): string => $state->color()),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(ProcessOrderStatus::options()),
            ])
            ->actions([
                // Open the Process Orders list filtered to this order, where its Edit
                // (add materials) and run actions (Issue → Costs → Produce → QC) live.
                Tables\Actions\Action::make('open')->label('Open / run')->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (ProcessOrder $record): string => ($record->isSubcontract()
                        ? KnittingSubcontractResource::getUrl('index')
                        : ProcessOrderResource::getUrl('index')).'?tableSearch='.urlencode($record->reference))
                    ->openUrlInNewTab(),
            ])
            ->emptyStateHeading('No process orders yet')
            ->emptyStateDescription('Use “Generate process order” on a stage to create the run for that step.');
    }
}
