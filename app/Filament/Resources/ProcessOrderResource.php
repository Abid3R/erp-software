<?php

namespace App\Filament\Resources;

use App\Actions\Process\IssueProcessMaterials;
use App\Actions\Process\RecordProcessCosts;
use App\Actions\Process\RecordProcessProduction;
use App\Actions\Process\RecordQualityInspection;
use App\Enums\ProcessOrderStatus;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\PostingException;
use App\Exceptions\ProcessException;
use App\Filament\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\ProcessOrderResource\Pages;
use App\Models\Batch;
use App\Models\LabDip;
use App\Models\ProcessOrder;
use App\Models\ProcessType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Textile process orders (knitting, dyeing, finishing, …) — the shared process
 * engine. A run consumes input materials and produces an output product/batch,
 * reusing the existing inventory + WIP + accounting engine. Status advances only
 * through the Issue / Produce / QC actions, which post the real effects.
 */
class ProcessOrderResource extends Resource
{
    protected static ?string $model = ProcessOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Manufacturing';

    protected static ?string $navigationLabel = 'Process Orders';

    protected static ?int $navigationSort = 7;

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::query()->whereIn('status', ['draft', 'planned', 'in_progress', 'qc'])->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Process')->columns(2)->schema([
                Forms\Components\Select::make('process_type_id')->label('Process')
                    ->relationship('processType', 'name', fn ($query) => $query->where('is_active', true))
                    ->required()->preload()->native(false)->live(),
                Forms\Components\Select::make('lab_dip_id')->label('Approved lab dip')
                    ->options(fn (): array => LabDip::query()->approved()->get()
                        ->mapWithKeys(fn (LabDip $l): array => [$l->getKey() => $l->label()])->all())
                    ->searchable()
                    ->helperText('Required for dyeing — pick a customer/internally approved colour.')
                    ->visible(fn (Forms\Get $get): bool => $get('process_type_id') !== null
                        && (bool) ProcessType::query()->whereKey($get('process_type_id'))->value('requires_lab_dip'))
                    ->required(fn (Forms\Get $get): bool => $get('process_type_id') !== null
                        && (bool) ProcessType::query()->whereKey($get('process_type_id'))->value('requires_lab_dip')),
                Forms\Components\Select::make('warehouse_id')
                    ->relationship('warehouse', 'name')->searchable()->preload()->required(),
                Forms\Components\Select::make('output_product_id')->label('Output product')
                    ->relationship('outputProduct', 'name')->searchable()->preload()->required()
                    ->helperText('e.g. grey fabric, dyed fabric, finished fabric.'),
                Forms\Components\TextInput::make('planned_quantity')->numeric()->minValue(0.0001)->default(1)->required(),
                Forms\Components\Select::make('manufacturing_order_id')->label('Manufacturing order (optional)')
                    ->relationship('manufacturingOrder', 'reference')
                    ->getOptionLabelFromRecordUsing(fn ($record): string => (string) ($record->reference ?? '#'.$record->getKey()))
                    ->searchable()->preload(),
                Forms\Components\Select::make('machine_id')
                    ->relationship('machine', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record): string => (string) ($record->name ?? $record->code ?? '#'.$record->getKey()))
                    ->searchable()->preload(),
                Forms\Components\Select::make('operator_id')
                    ->relationship('operator', 'employee_code')
                    ->getOptionLabelFromRecordUsing(fn ($record): string => (string) ($record->employee_code
                        ?? trim(($record->first_name ?? '').' '.($record->last_name ?? ''))
                        ?: '#'.$record->getKey()))
                    ->searchable()->preload(),
                Forms\Components\TextInput::make('notes')->maxLength(255)->columnSpanFull(),
            ]),
            Forms\Components\Section::make('Inputs (materials to consume)')->schema([
                Forms\Components\Repeater::make('inputs')->relationship()->schema([
                    Forms\Components\Select::make('product_id')->label('Material')
                        ->relationship('product', 'name')->searchable()->preload()->required()->live(),
                    Forms\Components\TextInput::make('planned_quantity')->numeric()->minValue(0.0001)->required(),
                    Forms\Components\Select::make('batch_id')->label('Batch (optional)')
                        ->options(fn (Forms\Get $get): array => $get('product_id')
                            ? Batch::query()->where('product_id', $get('product_id'))->orderByDesc('id')->pluck('batch_number', 'id')->all()
                            : [])
                        ->searchable()
                        ->helperText('Link the specific lot consumed (e.g. grey-fabric batch), for traceability.'),
                ])->columns(3)->addActionLabel('Add material')->defaultItems(1)->columnSpanFull(),
            ]),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('processType.name')->label('Process')->badge()->sortable(),
                Tables\Columns\TextColumn::make('outputProduct.name')->label('Output')->searchable(),
                Tables\Columns\TextColumn::make('planned_quantity')->label('Planned'),
                Tables\Columns\TextColumn::make('produced_quantity')->label('Produced'),
                Tables\Columns\TextColumn::make('wastage_quantity')->label('Wastage')->placeholder('—'),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (ProcessOrderStatus $state): string => $state->label())
                    ->color(fn (ProcessOrderStatus $state): string => $state->color()),
                Tables\Columns\TextColumn::make('machine.name')->label('Machine')->placeholder('—'),
                Tables\Columns\TextColumn::make('wip_cost')->label('WIP')->money(config('erp.currency.code'))->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('process_type_id')->label('Process')
                    ->options(fn (): array => ProcessType::query()->orderBy('sort')->pluck('name', 'id')->all()),
                Tables\Filters\SelectFilter::make('status')->options(ProcessOrderStatus::options()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\Action::make('issue')->label('Issue materials')
                    ->icon('heroicon-o-arrow-right-on-rectangle')->color('warning')
                    ->visible(fn (ProcessOrder $record): bool => $record->status->isOpen() && $record->started_at === null)
                    ->requiresConfirmation()
                    ->modalDescription('Consumes the input materials from stock and capitalises them into WIP. Cannot be undone.')
                    ->action(function (ProcessOrder $record): void {
                        try {
                            app(IssueProcessMaterials::class)->handle($record);
                            Notification::make()->title('Materials issued')->body('Inputs moved into WIP.')->success()->send();
                        } catch (ProcessException|InsufficientStockException|PostingException $e) {
                            Notification::make()->title('Cannot issue materials')->body($e->getMessage())->danger()->send();
                        }
                    }),

                Tables\Actions\Action::make('addCosts')->label('Add costs')
                    ->icon('heroicon-o-currency-bangladeshi')->color('gray')
                    ->visible(fn (ProcessOrder $record): bool => $record->status === ProcessOrderStatus::InProgress)
                    ->modalHeading('Conversion costs (capitalised into WIP)')
                    ->form([
                        Forms\Components\TextInput::make('labour')->label('Labour cost')->numeric()->minValue(0)->default(0)
                            ->prefix(config('erp.currency.symbol')),
                        Forms\Components\TextInput::make('machine_hours')->label('Machine hours')->numeric()->minValue(0)->default(0)
                            ->helperText('Costed at the machine hourly rate.'),
                        Forms\Components\TextInput::make('utility')->label('Utilities / electricity')->numeric()->minValue(0)->default(0)
                            ->prefix(config('erp.currency.symbol')),
                        Forms\Components\TextInput::make('overhead')->label('Other overhead')->numeric()->minValue(0)->default(0)
                            ->prefix(config('erp.currency.symbol')),
                    ])
                    ->action(function (ProcessOrder $record, array $data): void {
                        try {
                            app(RecordProcessCosts::class)->handle($record, $data);
                            Notification::make()->title('Costs added')->body('Conversion costs capitalised into WIP.')->success()->send();
                        } catch (ProcessException|\App\Exceptions\PostingException $e) {
                            Notification::make()->title('Cannot add costs')->body($e->getMessage())->danger()->send();
                        }
                    }),

                Tables\Actions\Action::make('produce')->label('Record production')
                    ->icon('heroicon-o-plus-circle')->color('info')
                    ->visible(fn (ProcessOrder $record): bool => $record->status === ProcessOrderStatus::InProgress)
                    ->form(fn (ProcessOrder $record): array => [
                        Forms\Components\TextInput::make('produced')->label('Produced quantity')
                            ->numeric()->minValue(0.0001)->maxValue((float) (string) $record->remainingToProduce())
                            ->default((float) (string) $record->remainingToProduce())->required()
                            ->helperText('Remaining: '.rtrim(rtrim((string) $record->remainingToProduce(), '0'), '.')),
                        Forms\Components\TextInput::make('wastage')->label('Wastage quantity')
                            ->numeric()->minValue(0)->default(0),
                    ])
                    ->action(function (ProcessOrder $record, array $data): void {
                        try {
                            app(RecordProcessProduction::class)->handle($record, (string) $data['produced'], (string) ($data['wastage'] ?? '0'));
                            Notification::make()->title('Production recorded')->body('Output received into stock with a new batch.')->success()->send();
                        } catch (ProcessException|PostingException $e) {
                            Notification::make()->title('Cannot record production')->body($e->getMessage())->danger()->send();
                        }
                    }),

                Tables\Actions\Action::make('recordQc')->label('Record QC')
                    ->icon('heroicon-o-check-badge')->color('success')
                    ->visible(fn (ProcessOrder $record): bool => $record->status === ProcessOrderStatus::Qc)
                    ->modalHeading('Quality inspection')
                    ->form(fn (ProcessOrder $record): array => [
                        Forms\Components\TextInput::make('inspected')->label('Inspected quantity')
                            ->numeric()->minValue(0)->default((float) (string) $record->produced_quantity)->required(),
                        Forms\Components\TextInput::make('passed')->label('Passed quantity')
                            ->numeric()->minValue(0)->default((float) (string) $record->produced_quantity)->required(),
                        Forms\Components\TextInput::make('rejected')->label('Rejected quantity')
                            ->numeric()->minValue(0)->maxValue((float) (string) $record->produced_quantity)->default(0)->required()
                            ->helperText('Rejected quantity is removed from available stock.'),
                        Forms\Components\Textarea::make('defects')->rows(2),
                        Forms\Components\Textarea::make('remarks')->rows(2),
                    ])
                    ->action(function (ProcessOrder $record, array $data): void {
                        try {
                            app(RecordQualityInspection::class)->handle(
                                $record, (string) $data['inspected'], (string) $data['passed'],
                                (string) ($data['rejected'] ?? '0'), $data['defects'] ?? null, $data['remarks'] ?? null,
                            );
                            Notification::make()->title('QC recorded')->body('Order completed; any rejects removed from stock.')->success()->send();
                        } catch (ProcessException|\App\Exceptions\PostingException $e) {
                            Notification::make()->title('Cannot record QC')->body($e->getMessage())->danger()->send();
                        }
                    }),

                Tables\Actions\EditAction::make()
                    ->visible(fn (ProcessOrder $record): bool => $record->status->isOpen() && $record->started_at === null),
                Tables\Actions\Action::make('cancel')->icon('heroicon-o-x-mark')->color('danger')
                    ->visible(fn (ProcessOrder $record): bool => $record->status->isOpen() && $record->started_at === null)
                    ->requiresConfirmation()
                    ->action(fn (ProcessOrder $record) => $record->update(['status' => ProcessOrderStatus::Cancelled])),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            DocumentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProcessOrders::route('/'),
            'create' => Pages\CreateProcessOrder::route('/create'),
            'view' => Pages\ViewProcessOrder::route('/{record}'),
            'edit' => Pages\EditProcessOrder::route('/{record}/edit'),
        ];
    }
}
