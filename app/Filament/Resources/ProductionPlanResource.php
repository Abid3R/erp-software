<?php

namespace App\Filament\Resources;

use App\Enums\ProductionPlanStatus;
use App\Filament\Resources\ProductionPlanResource\Pages;
use App\Filament\Resources\ProductionPlanResource\RelationManagers\StagesRelationManager;
use App\Models\ProductionPlan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Master production schedule (Time & Action plan). Links a customer order to its
 * ordered production stages (knitting → dyeing → finishing) with target quantities
 * and dates. Each stage spawns a real process order; progress is a live roll-up of
 * those runs.
 */
class ProductionPlanResource extends Resource
{
    protected static ?string $model = ProductionPlan::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Textile';

    protected static ?string $navigationLabel = 'Production Plans';

    protected static ?int $navigationSort = 5;

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::query()->open()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Plan')->columns(2)->schema([
                Forms\Components\Select::make('sales_order_id')->label('Customer order')
                    ->relationship('salesOrder', 'so_number')
                    ->getOptionLabelFromRecordUsing(fn ($record): string => (string) ($record->so_number ?? '#'.$record->getKey()))
                    ->searchable()->preload(),
                Forms\Components\Select::make('customer_id')->relationship('customer', 'name')
                    ->searchable()->preload(),
                Forms\Components\Select::make('product_id')->label('Main finished good')
                    ->relationship('product', 'name')->searchable()->preload(),
                Forms\Components\Select::make('status')->options(ProductionPlanStatus::options())
                    ->default(ProductionPlanStatus::Draft->value)->native(false)->required(),
            ]),
            Forms\Components\Section::make('Order & buyer specification')->columns(3)->schema([
                Forms\Components\TextInput::make('buyer')->maxLength(255)->placeholder('e.g. H&M'),
                Forms\Components\TextInput::make('style_no')->label('Style / Article no')->maxLength(64),
                Forms\Components\TextInput::make('po_no')->label('Buyer PO no')->maxLength(64),
                Forms\Components\TextInput::make('order_quantity')->label('Order qty')->numeric()->minValue(0),
                Forms\Components\TextInput::make('order_unit')->label('Order unit')->maxLength(16)->placeholder('PCS / DZN'),
                Forms\Components\TextInput::make('colour')->maxLength(255),
            ]),
            Forms\Components\Section::make('Fabric specification')->columns(4)->schema([
                Forms\Components\TextInput::make('fabric_composition')->label('Composition')->maxLength(255)->columnSpan(2)
                    ->placeholder('e.g. 80% Cotton 20% Polyester'),
                Forms\Components\TextInput::make('gsm')->label('GSM')->maxLength(32),
                Forms\Components\TextInput::make('fabric_width')->label('Width / Dia')->maxLength(64),
                Forms\Components\TextInput::make('fabric_type')->label('Fabric type')->maxLength(64)->placeholder('e.g. Single Jersey'),
                Forms\Components\TextInput::make('planned_quantity')->label('Production qty')->numeric()->minValue(0)->default(0)->required(),
                Forms\Components\TextInput::make('unit')->maxLength(16)->placeholder('e.g. KG'),
            ]),
            Forms\Components\Section::make('Schedule (Time & Action)')->columns(4)->schema([
                Forms\Components\DatePicker::make('booking_date'),
                Forms\Components\DatePicker::make('plan_date')->default(now()),
                Forms\Components\DatePicker::make('start_date'),
                Forms\Components\DatePicker::make('due_date')->label('Delivery / due'),
                Forms\Components\DatePicker::make('shipment_date'),
                Forms\Components\Textarea::make('notes')->rows(2)->columnSpan(3),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('buyer')->placeholder('—')->searchable(),
                Tables\Columns\TextColumn::make('style_no')->label('Style')->placeholder('—')->searchable(),
                Tables\Columns\TextColumn::make('customer.name')->label('Customer')->placeholder('—')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('colour')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('planned_quantity')->label('Planned'),
                Tables\Columns\TextColumn::make('progress')->label('Progress')
                    ->state(fn (ProductionPlan $record): string => number_format($record->progressPercent(), 1).'%'),
                Tables\Columns\TextColumn::make('due_date')->date()->sortable()->placeholder('—'),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (ProductionPlanStatus $state): string => $state->label())
                    ->color(fn (ProductionPlanStatus $state): string => $state->color()),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(ProductionPlanStatus::options()),
            ])
            ->actions([
                Tables\Actions\Action::make('print')->label('Plan sheet')
                    ->icon('heroicon-o-printer')->color('gray')
                    ->url(fn (ProductionPlan $record): string => route('print.production-plan', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            StagesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductionPlans::route('/'),
            'create' => Pages\CreateProductionPlan::route('/create'),
            'view' => Pages\ViewProductionPlan::route('/{record}'),
            'edit' => Pages\EditProductionPlan::route('/{record}/edit'),
        ];
    }
}
