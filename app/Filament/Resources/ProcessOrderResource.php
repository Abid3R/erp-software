<?php

namespace App\Filament\Resources;

use App\Actions\Process\CreateReworkOrder;
use App\Actions\Process\GenerateRolls;
use App\Actions\Process\IssueProcessMaterials;
use App\Actions\Process\RecordProcessCosts;
use App\Actions\Process\RecordProcessProduction;
use App\Actions\Process\RecordQualityInspection;
use App\Domain\Textile\MaterialRequirement;
use App\Enums\ProcessCategory;
use App\Enums\ProcessMode;
use App\Enums\ProcessOrderStatus;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\PostingException;
use App\Exceptions\ProcessException;
use App\Filament\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\ProcessOrderResource\Pages;
use App\Models\Batch;
use App\Models\DyeingSpecification;
use App\Models\LabDip;
use App\Models\ProcessOrder;
use App\Models\ProcessType;
use App\Models\ProductSpecification;
use Illuminate\Support\Str;
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

    protected static ?string $navigationGroup = 'Textile';

    protected static ?string $navigationLabel = 'Process Orders';

    protected static ?int $navigationSort = 7;

    /** In-house runs only — sub-contract (job-work) orders have their own resource. */
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->where('mode', ProcessMode::InHouse->value);
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::query()->where('mode', ProcessMode::InHouse->value)
            ->whereIn('status', ['draft', 'planned', 'in_progress', 'qc'])->count();

        return $count > 0 ? (string) $count : null;
    }

    /** The textile category of the currently-selected process type (drives spec fields). */
    public static function selectedCategory(Forms\Get $get): ?string
    {
        $id = $get('process_type_id');
        $category = $id ? ProcessType::query()->whereKey($id)->value('category') : null;

        // `value()` applies the model cast, so this may come back as the enum.
        return $category instanceof ProcessCategory ? $category->value : $category;
    }

    /** Fill the form's fabric/knitting + dyeing fields from a product's specification masters. */
    public static function applyProductSpecification(mixed $productId, callable $set): void
    {
        if (empty($productId)) {
            return;
        }
        $id = (int) $productId;

        // Knitting / fabric specification.
        $fabric = ProductSpecification::query()->where('product_id', $id)->first();
        if ($fabric !== null) {
            foreach (['fabric_composition', 'gsm', 'fabric_width', 'colour', 'colour_ref'] as $field) {
                if (filled($fabric->{$field})) {
                    $set($field, $fabric->{$field});
                }
            }
            foreach ($fabric->toSpecificationsArray() as $key => $value) {
                $set('specifications.'.$key, $value);
            }
        }

        // Dyeing specification (for a dyed-fabric product).
        $dye = DyeingSpecification::query()->where('product_id', $id)->first();
        if ($dye !== null) {
            foreach (['fabric_composition' => null, 'colour' => $dye->colour, 'colour_ref' => $dye->colour_ref, 'gsm' => $dye->gsm] as $field => $value) {
                if (filled($value)) {
                    $set($field, $value);
                }
            }
            foreach ($dye->toSpecificationsArray() as $key => $value) {
                $set('specifications.'.$key, (string) $value);
            }
        }
    }

    /** Fill the form's colour + dyeing parameters from an approved lab dip (the recipe master). */
    public static function applyLabDip(mixed $labDipId, callable $set): void
    {
        if (empty($labDipId)) {
            return;
        }
        $dip = LabDip::query()->whereKey((int) $labDipId)->first();
        if ($dip === null) {
            return;
        }
        foreach (['colour', 'colour_ref'] as $field) {
            if (filled($dip->{$field})) {
                $set($field, $dip->{$field});
            }
        }
        $map = array_filter([
            'process' => $dip->dyeing_process,
            'liquor_ratio' => $dip->liquor_ratio,
            'temperature' => $dip->temperature,
            'shade_percentage' => $dip->shade_percentage,
        ], fn ($v): bool => $v !== null && $v !== '');
        foreach ($map as $key => $value) {
            $set('specifications.'.$key, (string) $value);
        }
    }

    /**
     * Calculate the material requirement from the recipe (fabric spec for knitting,
     * lab dip for dyeing) against the planned quantity, and fill the inputs repeater.
     * Existing inputs not in the recipe (e.g. the grey-fabric substrate on a dyeing
     * order) are kept; recipe materials are added/replaced with the computed quantity.
     */
    public static function applyRecipeToInputs(Forms\Get $get, Forms\Set $set): void
    {
        $qty = $get('planned_quantity');
        if (blank($qty) || (float) $qty <= 0) {
            Notification::make()->title('Set the planned quantity first')->warning()->send();

            return;
        }

        $consumptions = collect();
        $liquor = null;
        $labDipId = $get('lab_dip_id');

        if (self::selectedCategory($get) === ProcessCategory::Dyeing->value && filled($labDipId)) {
            // Shade-specific recipe from the approved lab dip (highest precedence).
            $dip = LabDip::with('consumptions.product')->find($labDipId);
            $consumptions = $dip?->consumptions ?? collect();
            $liquor = LabDip::parseLiquorFactor($get('specifications.liquor_ratio') ?: $dip?->liquor_ratio);
        } elseif (filled($get('output_product_id'))) {
            $out = (int) $get('output_product_id');
            // Then the product's dyeing specification (standard dyeing program)…
            $dye = DyeingSpecification::with('consumptions.product')->where('product_id', $out)->first();
            if ($dye !== null && $dye->consumptions->isNotEmpty()) {
                $consumptions = $dye->consumptions;
                $liquor = LabDip::parseLiquorFactor($get('specifications.liquor_ratio') ?: $dye->liquor_ratio);
            } else {
                // …else the fabric/knitting specification.
                $spec = ProductSpecification::with('consumptions.product')->where('product_id', $out)->first();
                $consumptions = $spec?->consumptions ?? collect();
            }
        }

        if ($consumptions->isEmpty()) {
            Notification::make()->title('No recipe found')
                ->body('Define a material consumption recipe on the fabric specification (knitting) or lab dip (dyeing) first.')
                ->warning()->send();

            return;
        }

        $lines = MaterialRequirement::calculate($consumptions, (string) $qty, $liquor);
        $recipeProductIds = array_map(fn (array $l): int => $l['product_id'], $lines);

        // Keep manually-added inputs that the recipe does not cover (e.g. grey fabric).
        $rows = [];
        foreach ((array) ($get('inputs') ?? []) as $row) {
            if (! in_array((int) ($row['product_id'] ?? 0), $recipeProductIds, true)) {
                $rows[(string) Str::uuid()] = $row;
            }
        }
        foreach ($lines as $line) {
            $rows[(string) Str::uuid()] = [
                'product_id' => $line['product_id'],
                'planned_quantity' => (string) $line['required'],
                'batch_id' => null,
            ];
        }

        $set('inputs', $rows);
        Notification::make()->title('Materials calculated')
            ->body('Required quantities filled from the recipe, including wastage.')->success()->send();
    }

    /**
     * Shared fabric + technical specification fields (used by both the in-house
     * process order and the knitting sub-contract). Fabric header attributes are
     * real columns; the machine/dye parameters live in the `specifications` JSON and
     * are shown by process category.
     *
     * @return array<int, \Filament\Forms\Components\Component>
     */
    public static function specificationSection(): array
    {
        return [
            Forms\Components\Section::make('Fabric & specification')->columns(3)->schema([
                Forms\Components\TextInput::make('fabric_composition')->label('Composition')->maxLength(255)
                    ->placeholder('e.g. 80% Cotton 20% Polyester')->columnSpan(2),
                Forms\Components\TextInput::make('gsm')->label('GSM')->maxLength(32)->placeholder('e.g. 280/290'),
                Forms\Components\TextInput::make('fabric_width')->label('Width / Dia')->maxLength(64)
                    ->placeholder('e.g. 72 Inch Open'),
                Forms\Components\TextInput::make('colour')->label('Colour')->maxLength(255),
                Forms\Components\TextInput::make('colour_ref')->label('Colour ref')->maxLength(255),

                // Knitting parameters (machine dia / gauge / stitch length) → JSON.
                Forms\Components\Fieldset::make('Knitting parameters')
                    ->visible(fn (Forms\Get $get): bool => self::selectedCategory($get) === ProcessCategory::Knitting->value)
                    ->schema([
                        Forms\Components\TextInput::make('specifications.machine_diameter')->label('Machine dia')
                            ->placeholder('e.g. 30'),
                        Forms\Components\TextInput::make('specifications.gauge')->label('Gauge')->placeholder('e.g. 24'),
                        Forms\Components\TextInput::make('specifications.stitch_length')->label('Stitch length')
                            ->placeholder('e.g. 4.1+5.0+0.5'),
                        Forms\Components\TextInput::make('specifications.yarn_count')->label('Yarn count')
                            ->placeholder('e.g. 30s'),
                        Forms\Components\TextInput::make('specifications.fabric_type')->label('Fabric type')
                            ->placeholder('e.g. Single Jersey'),
                        Forms\Components\TextInput::make('specifications.quality')->label('Quality / grade')
                            ->placeholder('e.g. Combed'),
                    ])->columns(3)->columnSpanFull(),

                // Dyeing parameters → JSON.
                Forms\Components\Fieldset::make('Dyeing parameters')
                    ->visible(fn (Forms\Get $get): bool => self::selectedCategory($get) === ProcessCategory::Dyeing->value)
                    ->schema([
                        Forms\Components\TextInput::make('specifications.process')->label('Dyeing process')
                            ->placeholder('e.g. Reactive'),
                        Forms\Components\TextInput::make('specifications.liquor_ratio')->label('Liquor ratio')
                            ->placeholder('e.g. 1:8'),
                        Forms\Components\TextInput::make('specifications.temperature')->label('Temp (°C)'),
                        Forms\Components\TextInput::make('specifications.shade_percentage')->label('Shade %'),
                    ])->columns(2)->columnSpanFull(),
            ]),
        ];
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
                    ->live()
                    ->afterStateUpdated(fn ($state, Forms\Set $set) => self::applyLabDip($state, $set))
                    ->helperText('Required for dyeing — pick a customer/internally approved colour. Its recipe auto-fills below.')
                    ->visible(fn (Forms\Get $get): bool => $get('process_type_id') !== null
                        && (bool) ProcessType::query()->whereKey($get('process_type_id'))->value('requires_lab_dip'))
                    ->required(fn (Forms\Get $get): bool => $get('process_type_id') !== null
                        && (bool) ProcessType::query()->whereKey($get('process_type_id'))->value('requires_lab_dip')),
                Forms\Components\Select::make('warehouse_id')
                    ->relationship('warehouse', 'name')->searchable()->preload()->required(),
                Forms\Components\Select::make('output_product_id')->label('Output product')
                    ->relationship('outputProduct', 'name')->searchable()->preload()->required()
                    ->live()
                    ->afterStateUpdated(fn ($state, Forms\Set $set) => self::applyProductSpecification($state, $set))
                    ->helperText('e.g. grey fabric, dyed fabric, finished fabric. Its saved specification auto-fills below.'),
                Forms\Components\TextInput::make('planned_quantity')->numeric()->minValue(0.0001)->default(1)->required(),
                Forms\Components\TextInput::make('expected_wastage_percent')->label('Expected wastage %')
                    ->numeric()->minValue(0)->suffix('%')
                    ->helperText('Defaults from the process/product; override if needed.'),
                Forms\Components\Select::make('manufacturing_order_id')->label('Manufacturing order (optional)')
                    ->relationship('manufacturingOrder', 'reference')
                    ->getOptionLabelFromRecordUsing(fn ($record): string => (string) ($record->reference ?? '#'.$record->getKey()))
                    ->searchable()->preload(),
                Forms\Components\Select::make('sales_order_id')->label('Customer order (optional)')
                    ->relationship('salesOrder', 'so_number')
                    ->getOptionLabelFromRecordUsing(fn ($record): string => (string) ($record->so_number ?? '#'.$record->getKey()))
                    ->searchable()->preload()
                    ->helperText('Link this run to the customer order it fulfils.'),
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
            ...self::specificationSection(),
            Forms\Components\Section::make('Inputs (materials to consume)')->schema([
                Forms\Components\Actions::make([
                    Forms\Components\Actions\Action::make('calcFromRecipe')->label('Calculate from recipe')
                        ->icon('heroicon-o-calculator')->color('info')
                        ->action(fn (Forms\Get $get, Forms\Set $set) => self::applyRecipeToInputs($get, $set)),
                ]),
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
                Tables\Columns\TextColumn::make('is_rework')->label('Type')->badge()->toggleable()
                    ->formatStateUsing(fn ($state): string => $state ? 'Rework' : 'Normal')
                    ->color(fn ($state): string => $state ? 'warning' : 'gray'),
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

                Tables\Actions\Action::make('jobCard')->label('Job card')
                    ->icon('heroicon-o-printer')->color('gray')
                    ->url(fn (ProcessOrder $record): string => route('print.process-job-card', $record))
                    ->openUrlInNewTab(),

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
                            ->numeric()->minValue(0)->default(0)
                            ->helperText(fn (ProcessOrder $record): string => 'Expected output at '.rtrim(rtrim((string) ($record->expected_wastage_percent ?? '0'), '0'), '.').'% wastage: '.rtrim(rtrim((string) $record->expectedOutput(), '0'), '.')),
                        Forms\Components\TextInput::make('wastage_reason')->label('Wastage reason (optional)')->maxLength(255),
                        Forms\Components\TextInput::make('rolls')->label('Number of rolls')->numeric()->minValue(1)->default(1)
                            ->visible(fn (): bool => (bool) $record->outputProduct?->is_roll_tracked)
                            ->helperText('The produced quantity is split into this many rolls.'),
                    ])
                    ->action(function (ProcessOrder $record, array $data): void {
                        try {
                            app(RecordProcessProduction::class)->handle($record, (string) $data['produced'], (string) ($data['wastage'] ?? '0'));
                            if (filled($data['wastage_reason'] ?? null)) {
                                $record->update(['wastage_reason' => $data['wastage_reason']]);
                            }
                            if ($record->refresh()->outputProduct?->is_roll_tracked && (int) ($data['rolls'] ?? 0) > 0) {
                                app(GenerateRolls::class)->handle($record, (string) $data['produced'], (int) $data['rolls']);
                            }
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

                Tables\Actions\Action::make('rework')->label('Create rework')
                    ->icon('heroicon-o-arrow-path')->color('warning')
                    ->visible(fn (ProcessOrder $record): bool => ! $record->is_rework
                        && $record->status === ProcessOrderStatus::Completed
                        && $record->qualityInspections()->where('rejected_quantity', '>', 0)->exists())
                    ->modalHeading('Raise a rework run')
                    ->modalDescription('Creates a new linked run to reprocess the rejected output. The original order is not changed.')
                    ->form(fn (ProcessOrder $record): array => [
                        Forms\Components\TextInput::make('quantity')->label('Rework quantity')
                            ->numeric()->minValue(0.0001)
                            ->default((float) (string) $record->qualityInspections()->sum('rejected_quantity'))->required(),
                        Forms\Components\Textarea::make('notes')->rows(2),
                    ])
                    ->action(function (ProcessOrder $record, array $data): void {
                        $rework = app(CreateReworkOrder::class)->handle($record, (string) $data['quantity'], $data['notes'] ?? null);
                        Notification::make()->title('Rework created')
                            ->body('Created '.$rework->reference.' — add materials and run it through issue → produce → QC.')->success()->send();
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
