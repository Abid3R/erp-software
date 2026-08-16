<?php

namespace App\Filament\Resources;

use App\Actions\Manufacturing\CompleteManufacturingOrder;
use App\Actions\Manufacturing\IssueManufacturingMaterials;
use App\Actions\Manufacturing\RecordProduction;
use App\Domain\Manufacturing\MaterialAvailability;
use App\Enums\ManufacturingOrderStatus;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\ManufacturingException;
use App\Exceptions\PostingException;
use App\Filament\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\ManufacturingOrderResource\Pages;
use App\Filament\Resources\ManufacturingOrderResource\RelationManagers\InventoryMovementsRelationManager;
use App\Models\BillOfMaterials;
use App\Models\ManufacturingOrder;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ManufacturingOrderResource extends Resource
{
    protected static ?string $model = ManufacturingOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Manufacturing';

    protected static ?string $navigationLabel = 'Manufacturing Orders';

    protected static ?int $navigationSort = 3;

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::query()->whereIn('status', ['draft', 'planned', 'in_progress'])->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('reference')->maxLength(255),
            Forms\Components\Select::make('bill_of_materials_id')->label('Bill of materials')
                ->options(fn (): array => BillOfMaterials::query()->with('product')->where('is_active', true)->get()
                    ->mapWithKeys(fn (BillOfMaterials $b) => [$b->getKey() => ((string) data_get($b, 'product.name', '?')).' — '.$b->name])->all())
                ->searchable()->preload()->live()
                ->afterStateUpdated(function ($state, Forms\Set $set): void {
                    $bom = $state !== null ? BillOfMaterials::query()->find($state) : null;
                    if ($bom !== null) {
                        $set('product_id', $bom->product_id);
                    }
                })
                ->required(),
            Forms\Components\Select::make('product_id')->label('Finished product')
                ->relationship('product', 'name')->searchable()->preload()->required(),
            Forms\Components\Select::make('warehouse_id')
                ->relationship('warehouse', 'name')->searchable()->preload()->required(),
            Forms\Components\TextInput::make('quantity')->numeric()->minValue(0.0001)->default(1)->required(),
            Forms\Components\TextInput::make('notes')->maxLength(255)->columnSpanFull(),
            // Status is NOT editable here — it advances only through the Issue / Produce /
            // Complete actions, which post the inventory + accounting effects.
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference')->placeholder('—')->searchable(),
                Tables\Columns\TextColumn::make('product.name')->label('Finished product')->searchable(),
                Tables\Columns\TextColumn::make('quantity'),
                Tables\Columns\TextColumn::make('quantity_produced')->label('Produced'),
                Tables\Columns\TextColumn::make('warehouse.code')->label('Warehouse'),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (ManufacturingOrderStatus $state): string => $state->label())
                    ->color(fn (ManufacturingOrderStatus $state): string => $state->color()),
                Tables\Columns\TextColumn::make('total_cost')->money(config('erp.currency.code'))->placeholder('—'),
                Tables\Columns\TextColumn::make('wip_cost')->label('WIP')->money(config('erp.currency.code'))->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(ManufacturingOrderStatus::options()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                // Step 1 — check + issue materials into WIP.
                Tables\Actions\Action::make('issue')->label('Issue materials')->icon('heroicon-o-arrow-right-on-rectangle')->color('warning')
                    ->visible(fn (ManufacturingOrder $record): bool => $record->status->isOpen() && ! $record->materialsIssued())
                    ->modalHeading('Issue materials into production')
                    ->modalContent(fn (ManufacturingOrder $record) => view('filament.manufacturing.availability', [
                        'availability' => MaterialAvailability::for($record),
                    ]))
                    ->modalSubmitActionLabel('Issue materials')
                    ->action(function (ManufacturingOrder $record): void {
                        try {
                            app(IssueManufacturingMaterials::class)->handle($record);
                            Notification::make()->title('Materials issued')->body('Components moved into WIP.')->success()->send();
                        } catch (ManufacturingException|InsufficientStockException|PostingException $e) {
                            Notification::make()->title('Cannot issue materials')->body($e->getMessage())->danger()->send();
                        }
                    }),

                // Step 2 — record (possibly partial) production output.
                Tables\Actions\Action::make('produce')->label('Record production')->icon('heroicon-o-plus-circle')->color('info')
                    ->visible(fn (ManufacturingOrder $record): bool => $record->status === ManufacturingOrderStatus::InProgress)
                    ->form(fn (ManufacturingOrder $record): array => [
                        Forms\Components\TextInput::make('produced')->label('Produced quantity')
                            ->numeric()->minValue(0.0001)->maxValue((float) (string) $record->remainingToProduce())
                            ->default((float) (string) $record->remainingToProduce())->required()
                            ->helperText('Remaining to produce: '.rtrim(rtrim((string) $record->remainingToProduce(), '0'), '.')),
                    ])
                    ->action(function (ManufacturingOrder $record, array $data): void {
                        try {
                            app(RecordProduction::class)->handle($record, (string) $data['produced']);
                            Notification::make()->title('Production recorded')->body('Finished goods received into stock.')->success()->send();
                        } catch (ManufacturingException|PostingException $e) {
                            Notification::make()->title('Cannot record production')->body($e->getMessage())->danger()->send();
                        }
                    }),

                // One-click: issue everything + produce the full quantity.
                Tables\Actions\Action::make('complete')->label('Complete now')->icon('heroicon-o-check-badge')->color('success')
                    ->visible(fn (ManufacturingOrder $record): bool => $record->status->isOpen() && ! $record->materialsIssued())
                    ->requiresConfirmation()
                    ->modalDescription('Issues all BOM components and produces the full quantity in one step. It cannot be undone.')
                    ->action(function (ManufacturingOrder $record): void {
                        try {
                            app(CompleteManufacturingOrder::class)->handle($record);
                            Notification::make()->title('Order completed')
                                ->body('Components issued and finished goods received into stock.')->success()->send();
                        } catch (ManufacturingException|InsufficientStockException|PostingException $e) {
                            Notification::make()->title('Cannot complete')->body($e->getMessage())->danger()->send();
                        }
                    }),

                Tables\Actions\EditAction::make()
                    ->visible(fn (ManufacturingOrder $record): bool => $record->status->isOpen() && ! $record->materialsIssued()),
                Tables\Actions\Action::make('cancel')->icon('heroicon-o-x-mark')->color('danger')
                    ->visible(fn (ManufacturingOrder $record): bool => $record->status->isOpen() && ! $record->materialsIssued())
                    ->requiresConfirmation()
                    ->action(fn (ManufacturingOrder $record) => $record->update(['status' => ManufacturingOrderStatus::Cancelled])),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            InventoryMovementsRelationManager::class,
            DocumentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListManufacturingOrders::route('/'),
            'create' => Pages\CreateManufacturingOrder::route('/create'),
            'view' => Pages\ViewManufacturingOrder::route('/{record}'),
            'edit' => Pages\EditManufacturingOrder::route('/{record}/edit'),
        ];
    }
}
