<?php

namespace App\Filament\Resources;

use App\Actions\Inventory\PostStockAdjustment;
use App\Enums\StockAdjustmentDirection;
use App\Enums\StockAdjustmentStatus;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\PostingException;
use App\Exceptions\StockAdjustmentException;
use App\Filament\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\StockAdjustmentResource\Pages;
use App\Models\StockAdjustment;
use App\Support\CompanyContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class StockAdjustmentResource extends Resource
{
    protected static ?string $model = StockAdjustment::class;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?string $navigationLabel = 'Stock Adjustments';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('number')->required()->maxLength(32)
                ->default(fn (): string => 'ADJ-'.str_pad((string) (StockAdjustment::query()->count() + 1), 4, '0', STR_PAD_LEFT))
                ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('company_id', app(CompanyContext::class)->currentId())),
            Forms\Components\Select::make('warehouse_id')->relationship('warehouse', 'name')->searchable()->preload()->required(),
            Forms\Components\DatePicker::make('adjustment_date')->default(now())->required(),
            Forms\Components\TextInput::make('reason')->maxLength(255)->placeholder('e.g. physical count, damage, shrinkage'),
            Forms\Components\TextInput::make('notes')->maxLength(255)->columnSpanFull(),
            Forms\Components\Repeater::make('lines')->relationship()->schema([
                Forms\Components\Select::make('product_id')->label('Product')->relationship('product', 'name')->searchable()->preload()->required(),
                Forms\Components\Select::make('direction')->options(StockAdjustmentDirection::options())
                    ->default(StockAdjustmentDirection::In->value)->required()->live(),
                Forms\Components\TextInput::make('quantity')->numeric()->minValue(0.0001)->required(),
                Forms\Components\TextInput::make('unit_cost')->numeric()->minValue(0)
                    ->helperText('Cost for increases; decreases use average cost')
                    ->visible(fn (Forms\Get $get): bool => $get('direction') === StockAdjustmentDirection::In->value)
                    ->required(fn (Forms\Get $get): bool => $get('direction') === StockAdjustmentDirection::In->value),
            ])->columns(4)->minItems(1)->addActionLabel('Add line')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('warehouse.name')->searchable(),
                Tables\Columns\TextColumn::make('adjustment_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('reason')->limit(30)->placeholder('—'),
                Tables\Columns\TextColumn::make('lines_count')->counts('lines')->label('Lines'),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (StockAdjustmentStatus $state): string => $state->label())
                    ->color(fn (StockAdjustmentStatus $state): string => $state->color()),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(StockAdjustmentStatus::options()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (StockAdjustment $record): bool => $record->status === StockAdjustmentStatus::Draft),
                Tables\Actions\Action::make('post')->icon('heroicon-o-check-badge')->color('success')
                    ->visible(fn (StockAdjustment $record): bool => $record->status === StockAdjustmentStatus::Draft)
                    ->requiresConfirmation()
                    ->modalDescription('Posting moves the stock ledger and books the difference to the inventory-adjustment account. It cannot be undone.')
                    ->action(function (StockAdjustment $record): void {
                        try {
                            app(PostStockAdjustment::class)->handle($record);
                            Notification::make()->title('Stock adjustment posted')->body('Stock and accounting updated.')->success()->send();
                        } catch (StockAdjustmentException|InsufficientStockException|PostingException $e) {
                            Notification::make()->title('Cannot post adjustment')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('print')->icon('heroicon-o-printer')->color('gray')
                    ->url(fn (StockAdjustment $record): string => route('print.stock-adjustment', $record))->openUrlInNewTab(),
                Tables\Actions\Action::make('cancel')->icon('heroicon-o-x-mark')->color('danger')
                    ->visible(fn (StockAdjustment $record): bool => $record->status === StockAdjustmentStatus::Draft)
                    ->requiresConfirmation()
                    ->action(fn (StockAdjustment $record) => $record->update(['status' => StockAdjustmentStatus::Cancelled])),
            ])
            ->defaultSort('adjustment_date', 'desc');
    }

    public static function getRelations(): array
    {
        return [DocumentsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockAdjustments::route('/'),
            'create' => Pages\CreateStockAdjustment::route('/create'),
            'view' => Pages\ViewStockAdjustment::route('/{record}'),
            'edit' => Pages\EditStockAdjustment::route('/{record}/edit'),
        ];
    }
}
