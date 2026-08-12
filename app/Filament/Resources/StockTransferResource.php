<?php

namespace App\Filament\Resources;

use App\Actions\Inventory\PostStockTransfer;
use App\Enums\StockTransferStatus;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\StockTransferException;
use App\Filament\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\StockTransferResource\Pages;
use App\Models\StockTransfer;
use App\Support\CompanyContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class StockTransferResource extends Resource
{
    protected static ?string $model = StockTransfer::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?string $navigationLabel = 'Stock Transfers';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('number')->required()->maxLength(32)
                ->default(fn (): string => 'TRF-'.str_pad((string) (StockTransfer::query()->count() + 1), 4, '0', STR_PAD_LEFT))
                ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('company_id', app(CompanyContext::class)->currentId())),
            Forms\Components\Select::make('from_warehouse_id')->label('From warehouse')
                ->relationship('fromWarehouse', 'name')->searchable()->preload()->required()
                ->different('to_warehouse_id'),
            Forms\Components\Select::make('to_warehouse_id')->label('To warehouse')
                ->relationship('toWarehouse', 'name')->searchable()->preload()->required()
                ->different('from_warehouse_id'),
            Forms\Components\DatePicker::make('transfer_date')->default(now())->required(),
            Forms\Components\TextInput::make('notes')->maxLength(255)->columnSpanFull(),
            Forms\Components\Repeater::make('lines')->relationship()->schema([
                Forms\Components\Select::make('product_id')->label('Product')->relationship('product', 'name')->searchable()->preload()->required(),
                Forms\Components\TextInput::make('quantity')->numeric()->minValue(0.0001)->required(),
            ])->columns(2)->minItems(1)->addActionLabel('Add line')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('fromWarehouse.name')->label('From'),
                Tables\Columns\TextColumn::make('toWarehouse.name')->label('To'),
                Tables\Columns\TextColumn::make('transfer_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('lines_count')->counts('lines')->label('Lines'),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (StockTransferStatus $state): string => $state->label())
                    ->color(fn (StockTransferStatus $state): string => $state->color()),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(StockTransferStatus::options()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (StockTransfer $record): bool => $record->status === StockTransferStatus::Draft),
                Tables\Actions\Action::make('post')->icon('heroicon-o-check-badge')->color('success')
                    ->visible(fn (StockTransfer $record): bool => $record->status === StockTransferStatus::Draft)
                    ->requiresConfirmation()
                    ->modalDescription('Posting moves stock from the source to the destination warehouse at average cost. It cannot be undone.')
                    ->action(function (StockTransfer $record): void {
                        try {
                            app(PostStockTransfer::class)->handle($record);
                            Notification::make()->title('Stock transfer posted')->body('Stock moved between warehouses.')->success()->send();
                        } catch (StockTransferException|InsufficientStockException $e) {
                            Notification::make()->title('Cannot post transfer')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('print')->icon('heroicon-o-printer')->color('gray')
                    ->url(fn (StockTransfer $record): string => route('print.stock-transfer', $record))->openUrlInNewTab(),
                Tables\Actions\Action::make('cancel')->icon('heroicon-o-x-mark')->color('danger')
                    ->visible(fn (StockTransfer $record): bool => $record->status === StockTransferStatus::Draft)
                    ->requiresConfirmation()
                    ->action(fn (StockTransfer $record) => $record->update(['status' => StockTransferStatus::Cancelled])),
            ])
            ->defaultSort('transfer_date', 'desc');
    }

    public static function getRelations(): array
    {
        return [DocumentsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockTransfers::route('/'),
            'create' => Pages\CreateStockTransfer::route('/create'),
            'view' => Pages\ViewStockTransfer::route('/{record}'),
            'edit' => Pages\EditStockTransfer::route('/{record}/edit'),
        ];
    }
}
