<?php

namespace App\Filament\Resources\ProductionPlanResource\RelationManagers;

use App\Actions\Process\CreateProcessOrderFromStage;
use App\Enums\ProductionStageStatus;
use App\Models\Machine;
use App\Models\ProcessType;
use App\Models\ProductionPlanStage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * The ordered stages of a production plan. Each stage can spawn a real process
 * order (the execution); progress is rolled up from the linked runs.
 */
class StagesRelationManager extends RelationManager
{
    protected static string $relationship = 'stages';

    protected static ?string $title = 'Production stages';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('process_type_id')->label('Process')
                ->options(fn (): array => ProcessType::query()->where('is_active', true)->orderBy('sort')->pluck('name', 'id')->all())
                ->required()->native(false),
            Forms\Components\TextInput::make('sequence')->numeric()->minValue(1)->default(1)->required(),
            Forms\Components\Select::make('machine_id')->label('Machine')
                ->options(fn (): array => Machine::query()->where('is_active', true)->pluck('name', 'id')->all())
                ->searchable()->native(false),
            Forms\Components\Select::make('output_product_id')->label('Output product')
                ->relationship('outputProduct', 'name')->searchable()->preload(),
            Forms\Components\TextInput::make('planned_quantity')->numeric()->minValue(0)->default(0)->required(),
            Forms\Components\DatePicker::make('planned_start'),
            Forms\Components\DatePicker::make('planned_end'),
            Forms\Components\Textarea::make('notes')->rows(2)->columnSpanFull(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('sequence')
            ->columns([
                Tables\Columns\TextColumn::make('sequence')->label('#')->sortable(),
                Tables\Columns\TextColumn::make('processType.name')->label('Process')->badge(),
                Tables\Columns\TextColumn::make('machine.name')->label('Machine')->placeholder('—'),
                Tables\Columns\TextColumn::make('planned_quantity')->label('Planned'),
                Tables\Columns\TextColumn::make('produced')->label('Produced')
                    ->state(fn (ProductionPlanStage $record): string => (string) $record->producedQuantity()->toScale(2)),
                Tables\Columns\TextColumn::make('planned_start')->date()->placeholder('—'),
                Tables\Columns\TextColumn::make('planned_end')->date()->placeholder('—'),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (ProductionStageStatus $state): string => $state->label())
                    ->color(fn (ProductionStageStatus $state): string => $state->color()),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('createProcessOrder')->label('Generate process order')
                    ->icon('heroicon-o-play')->color('success')
                    ->visible(fn (ProductionPlanStage $record): bool => $record->status !== ProductionStageStatus::Skipped)
                    ->requiresConfirmation()
                    ->modalDescription('Creates a process order for this stage (carrying the process, machine, product and quantity) that you then run through issue → produce → QC.')
                    ->action(function (ProductionPlanStage $record): void {
                        $order = app(CreateProcessOrderFromStage::class)->handle($record);
                        Notification::make()->title('Process order created')
                            ->body('Created '.$order->reference.' — open Process Orders to run it.')->success()->send();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
