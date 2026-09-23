<?php

namespace App\Filament\Resources;

use App\Actions\Process\IssueProcessMaterials;
use App\Actions\Process\RecordProcessProduction;
use App\Actions\Process\RecordQualityInspection;
use App\Actions\Process\RecordSubcontractCharge;
use App\Enums\ProcessMode;
use App\Enums\ProcessOrderStatus;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\PostingException;
use App\Exceptions\ProcessException;
use App\Filament\Resources\KnittingSubcontractResource\Pages;
use App\Models\Batch;
use App\Models\ProcessOrder;
use App\Models\ProcessType;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Knitting sub-contract (job-work) — the Bangladesh practice of sending yarn to an
 * outside knitter who charges a rate per KG and returns grey fabric. It reuses the
 * shared process engine in sub-contract mode:
 *   Issue yarn → Record knitting charge (payable to the knitter) → Receive grey
 *   fabric → QC. The grey fabric absorbs yarn + knitting cost with full batch
 *   traceability. A single ProcessOrder row backs each order (mode = subcontract).
 */
class KnittingSubcontractResource extends Resource
{
    protected static ?string $model = ProcessOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path-rounded-square';

    protected static ?string $navigationGroup = 'Textile';

    protected static ?string $navigationLabel = 'Knitting Sub-contracts';

    protected static ?string $modelLabel = 'knitting sub-contract';

    protected static ?int $navigationSort = 8;

    /** Sub-contract (job-work) orders only. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('mode', ProcessMode::Subcontract->value);
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::query()->where('mode', ProcessMode::Subcontract->value)
            ->whereIn('status', ['draft', 'planned', 'in_progress', 'qc'])->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Hidden::make('mode')->default(ProcessMode::Subcontract->value),
            Forms\Components\Section::make('Sub-contract')->columns(2)->schema([
                Forms\Components\Select::make('process_type_id')->label('Process')
                    ->options(fn (): array => ProcessType::query()->where('is_active', true)
                        ->where('subcontractable', true)->pluck('name', 'id')->all())
                    ->default(fn (): ?int => ProcessType::query()->where('code', 'KNIT')->value('id'))
                    ->required()->native(false)->live(),
                Forms\Components\Select::make('subcontractor_id')->label('Sub-contractor')
                    ->relationship('subcontractor', 'name')->searchable()->preload()->required()
                    ->helperText('The outside knitter doing the job-work.'),
                Forms\Components\Select::make('warehouse_id')
                    ->relationship('warehouse', 'name')->searchable()->preload()->required(),
                Forms\Components\Select::make('output_product_id')->label('Output (grey fabric)')
                    ->relationship('outputProduct', 'name')->searchable()->preload()->required()
                    ->live()
                    ->afterStateUpdated(fn ($state, Forms\Set $set) => ProcessOrderResource::applyProductSpecification($state, $set))
                    ->afterStateHydrated(fn ($state, Forms\Get $get, Forms\Set $set) => ProcessOrderResource::hydrateSpecification($state, $get, $set))
                    ->helperText('Its saved specification auto-fills the fabric fields below.'),
                Forms\Components\TextInput::make('planned_quantity')->label('Planned output qty')
                    ->numeric()->minValue(0.0001)->default(1)->required(),
                Forms\Components\Select::make('sales_order_id')->label('Customer order (optional)')
                    ->relationship('salesOrder', 'so_number')
                    ->getOptionLabelFromRecordUsing(fn ($record): string => (string) ($record->so_number ?? '#'.$record->getKey()))
                    ->searchable()->preload(),
            ]),
            Forms\Components\Section::make('Service charge')->columns(3)
                ->description('Billed by the sub-contractor for the knitting service.')
                ->schema([
                    Forms\Components\Select::make('service_item_id')->label('Service item')
                        ->options(fn (): array => Product::query()->where('is_service', true)->pluck('name', 'id')->all())
                        ->searchable()->native(false)
                        ->helperText('A non-stock service, e.g. "Service Charge for Knitting".'),
                    Forms\Components\TextInput::make('bill_quantity')->label('Bill qty (KG)')->numeric()->minValue(0),
                    Forms\Components\TextInput::make('service_rate')->label('Service rate')->numeric()->minValue(0)
                        ->helperText('Per KG.'),
                    Forms\Components\TextInput::make('service_currency')->label('Currency')
                        ->default(config('erp.currency.code'))->maxLength(3),
                ]),
            ...ProcessOrderResource::specificationSection(),
            Forms\Components\Section::make('Yarn to issue')->schema([
                Forms\Components\Actions::make([
                    Forms\Components\Actions\Action::make('calcFromRecipe')->label('Calculate from recipe')
                        ->icon('heroicon-o-calculator')->color('info')
                        ->action(fn (Forms\Get $get, Forms\Set $set) => ProcessOrderResource::applyRecipeToInputs($get, $set)),
                ]),
                Forms\Components\Repeater::make('inputs')->relationship()->schema([
                    Forms\Components\Select::make('product_id')->label('Yarn / material')
                        ->relationship('product', 'name')->searchable()->preload()->required()->live(),
                    Forms\Components\TextInput::make('planned_quantity')->numeric()->minValue(0.0001)->required(),
                    Forms\Components\Select::make('batch_id')->label('Batch (optional)')
                        ->options(fn (Forms\Get $get): array => $get('product_id')
                            ? Batch::query()->where('product_id', $get('product_id'))->orderByDesc('id')->pluck('batch_number', 'id')->all()
                            : [])
                        ->searchable()->helperText('Link the yarn lot sent, for traceability.'),
                ])->columns(3)->addActionLabel('Add yarn')->defaultItems(1)->columnSpanFull(),
            ]),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('subcontractor.name')->label('Sub-contractor')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('outputProduct.name')->label('Grey fabric')->searchable(),
                Tables\Columns\TextColumn::make('planned_quantity')->label('Planned'),
                Tables\Columns\TextColumn::make('produced_quantity')->label('Received'),
                Tables\Columns\TextColumn::make('service_charge')->label('Charge')
                    ->money(config('erp.currency.code'))->placeholder('—'),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (ProcessOrderStatus $state): string => $state->label())
                    ->color(fn (ProcessOrderStatus $state): string => $state->color()),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(ProcessOrderStatus::options()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\Action::make('workOrder')->label('Work order')
                    ->icon('heroicon-o-printer')->color('gray')
                    ->url(fn (ProcessOrder $record): string => route('print.subcontract-work-order', $record))
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('issue')->label('Issue yarn')
                    ->icon('heroicon-o-arrow-right-on-rectangle')->color('warning')
                    ->visible(fn (ProcessOrder $record): bool => $record->status->isOpen() && $record->started_at === null)
                    ->requiresConfirmation()
                    ->modalDescription('Issues the yarn to the sub-contractor (into WIP). Cannot be undone.')
                    ->action(function (ProcessOrder $record): void {
                        try {
                            app(IssueProcessMaterials::class)->handle($record);
                            Notification::make()->title('Yarn issued')->body('Yarn moved into WIP.')->success()->send();
                        } catch (ProcessException|InsufficientStockException|PostingException $e) {
                            Notification::make()->title('Cannot issue yarn')->body($e->getMessage())->danger()->send();
                        }
                    }),

                Tables\Actions\Action::make('charge')->label('Record knitting charge')
                    ->icon('heroicon-o-currency-bangladeshi')->color('gray')
                    ->visible(fn (ProcessOrder $record): bool => $record->status === ProcessOrderStatus::InProgress
                        && $record->service_charged_at === null)
                    ->modalHeading('Knitting service charge (payable to the sub-contractor)')
                    ->form(fn (ProcessOrder $record): array => [
                        Forms\Components\TextInput::make('bill_quantity')->label('Bill qty (KG)')->numeric()->minValue(0.0001)
                            ->default(fn (): ?string => $record->bill_quantity ?? $record->planned_quantity)->required(),
                        Forms\Components\TextInput::make('service_rate')->label('Service rate (per KG)')->numeric()->minValue(0.0001)
                            ->default(fn (): ?string => $record->service_rate)->required(),
                    ])
                    ->action(function (ProcessOrder $record, array $data): void {
                        try {
                            app(RecordSubcontractCharge::class)->handle($record, $data);
                            Notification::make()->title('Charge recorded')->body('Knitting charge posted to the sub-contractor payable and capitalised into WIP.')->success()->send();
                        } catch (ProcessException|PostingException $e) {
                            Notification::make()->title('Cannot record charge')->body($e->getMessage())->danger()->send();
                        }
                    }),

                Tables\Actions\Action::make('produce')->label('Receive grey fabric')
                    ->icon('heroicon-o-plus-circle')->color('info')
                    ->visible(fn (ProcessOrder $record): bool => $record->status === ProcessOrderStatus::InProgress)
                    ->form(fn (ProcessOrder $record): array => [
                        Forms\Components\TextInput::make('produced')->label('Received quantity')
                            ->numeric()->minValue(0.0001)->maxValue((float) (string) $record->remainingToProduce())
                            ->default((float) (string) $record->remainingToProduce())->required(),
                        Forms\Components\TextInput::make('wastage')->label('Wastage / loss')->numeric()->minValue(0)->default(0),
                    ])
                    ->action(function (ProcessOrder $record, array $data): void {
                        try {
                            app(RecordProcessProduction::class)->handle($record, (string) $data['produced'], (string) ($data['wastage'] ?? '0'));
                            Notification::make()->title('Grey fabric received')->body('Received into stock as a traceable batch.')->success()->send();
                        } catch (ProcessException|PostingException $e) {
                            Notification::make()->title('Cannot receive fabric')->body($e->getMessage())->danger()->send();
                        }
                    }),

                Tables\Actions\Action::make('recordQc')->label('Record QC')
                    ->icon('heroicon-o-check-badge')->color('success')
                    ->visible(fn (ProcessOrder $record): bool => $record->status === ProcessOrderStatus::Qc)
                    ->form(fn (ProcessOrder $record): array => [
                        Forms\Components\TextInput::make('inspected')->label('Inspected')->numeric()->minValue(0)
                            ->default((float) (string) $record->produced_quantity)->required(),
                        Forms\Components\TextInput::make('passed')->label('Passed')->numeric()->minValue(0)
                            ->default((float) (string) $record->produced_quantity)->required(),
                        Forms\Components\TextInput::make('rejected')->label('Rejected')->numeric()->minValue(0)
                            ->maxValue((float) (string) $record->produced_quantity)->default(0)->required(),
                        Forms\Components\Textarea::make('defects')->rows(2),
                        Forms\Components\Textarea::make('remarks')->rows(2),
                    ])
                    ->action(function (ProcessOrder $record, array $data): void {
                        try {
                            app(RecordQualityInspection::class)->handle(
                                $record, (string) $data['inspected'], (string) $data['passed'],
                                (string) ($data['rejected'] ?? '0'), $data['defects'] ?? null, $data['remarks'] ?? null,
                            );
                            Notification::make()->title('QC recorded')->success()->send();
                        } catch (ProcessException|PostingException $e) {
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListKnittingSubcontracts::route('/'),
            'create' => Pages\CreateKnittingSubcontract::route('/create'),
            'view' => Pages\ViewKnittingSubcontract::route('/{record}'),
            'edit' => Pages\EditKnittingSubcontract::route('/{record}/edit'),
        ];
    }
}
