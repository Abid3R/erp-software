<?php

namespace App\Filament\Resources;

use App\Actions\Manufacturing\CompleteManufacturingOrder;
use App\Enums\ManufacturingOrderStatus;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\ManufacturingException;
use App\Filament\Resources\ManufacturingOrderResource\Pages;
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

    protected static ?int $navigationSort = 2;

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
            Forms\Components\Select::make('status')->options(ManufacturingOrderStatus::options())->default('planned')->required(),
            Forms\Components\TextInput::make('notes')->maxLength(255)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference')->placeholder('—')->searchable(),
                Tables\Columns\TextColumn::make('product.name')->label('Finished product')->searchable(),
                Tables\Columns\TextColumn::make('quantity'),
                Tables\Columns\TextColumn::make('warehouse.code')->label('Warehouse'),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (ManufacturingOrderStatus $s): string => $s->label())
                    ->color(fn (ManufacturingOrderStatus $s): string => $s->color()),
                Tables\Columns\TextColumn::make('total_cost')->money(config('erp.currency.code'))->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(ManufacturingOrderStatus::options()),
            ])
            ->actions([
                Tables\Actions\Action::make('complete')->icon('heroicon-o-check-badge')->color('success')
                    ->label('Complete')
                    ->visible(fn (ManufacturingOrder $record): bool => $record->status->isOpen())
                    ->requiresConfirmation()
                    ->modalDescription('This issues the BOM components from stock and receives the finished goods. It cannot be undone.')
                    ->action(function (ManufacturingOrder $record): void {
                        try {
                            app(CompleteManufacturingOrder::class)->handle($record);
                            Notification::make()->title('Order completed')
                                ->body('Components issued and finished goods received into stock.')->success()->send();
                        } catch (ManufacturingException|InsufficientStockException $e) {
                            Notification::make()->title('Cannot complete')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\EditAction::make()
                    ->visible(fn (ManufacturingOrder $record): bool => $record->status->isOpen()),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListManufacturingOrders::route('/'),
            'create' => Pages\CreateManufacturingOrder::route('/create'),
            'edit' => Pages\EditManufacturingOrder::route('/{record}/edit'),
        ];
    }
}
