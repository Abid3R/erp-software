<?php

namespace App\Filament\Resources;

use App\Actions\Purchasing\ReceivePurchaseOrder;
use App\Enums\PurchaseOrderStatus;
use App\Exceptions\PostingException;
use App\Exceptions\PurchaseOrderException;
use App\Filament\Resources\PurchaseOrderResource\Pages;
use App\Filament\Resources\PurchaseOrderResource\RelationManagers\LinesRelationManager;
use App\Models\PurchaseOrder;
use App\Support\CompanyContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PurchaseOrderResource extends Resource
{
    protected static ?string $model = PurchaseOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Purchasing';

    protected static ?string $navigationLabel = 'Purchase Orders';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::query()->whereIn('status', ['approved', 'partially_received'])->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('lines');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('po_number')->required()->maxLength(32)
                ->default(fn (): string => 'PO-'.str_pad((string) (PurchaseOrder::query()->count() + 1), 4, '0', STR_PAD_LEFT))
                ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('company_id', app(CompanyContext::class)->currentId())),
            Forms\Components\Select::make('supplier_id')->relationship('supplier', 'name')->searchable()->preload()->required(),
            Forms\Components\Select::make('warehouse_id')->relationship('warehouse', 'name')->searchable()->preload()->required(),
            Forms\Components\DatePicker::make('order_date')->default(now())->required(),
            Forms\Components\DatePicker::make('expected_date'),
            Forms\Components\TextInput::make('notes')->maxLength(255)->columnSpanFull(),
            Forms\Components\Repeater::make('lines')->relationship()->schema([
                Forms\Components\Select::make('product_id')->label('Product')->relationship('product', 'name')->searchable()->preload()->required(),
                Forms\Components\TextInput::make('quantity_ordered')->numeric()->minValue(0.0001)->required(),
                Forms\Components\TextInput::make('unit_price')->numeric()->minValue(0)->required()->prefix(config('erp.currency.symbol')),
            ])->columns(3)->minItems(1)->addActionLabel('Add line')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('po_number')->label('PO #')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('supplier.name')->searchable(),
                Tables\Columns\TextColumn::make('order_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (PurchaseOrderStatus $state): string => $state->label())
                    ->color(fn (PurchaseOrderStatus $state): string => $state->color()),
                Tables\Columns\TextColumn::make('total')->money(config('erp.currency.code'))
                    ->state(fn (PurchaseOrder $record): string => (string) $record->total()),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(PurchaseOrderStatus::options()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (PurchaseOrder $record): bool => $record->status === PurchaseOrderStatus::Draft),
                Tables\Actions\Action::make('approve')->icon('heroicon-o-check')->color('info')
                    ->visible(fn (PurchaseOrder $record): bool => $record->status === PurchaseOrderStatus::Draft)
                    ->requiresConfirmation()
                    ->action(function (PurchaseOrder $record): void {
                        $record->update(['status' => PurchaseOrderStatus::Approved]);
                        Notification::make()->title('Purchase order approved')->success()->send();
                    }),
                Tables\Actions\Action::make('receive')->icon('heroicon-o-truck')->color('success')
                    ->visible(fn (PurchaseOrder $record): bool => $record->status->isReceivable())
                    ->form(fn (PurchaseOrder $record): array => [
                        Forms\Components\DatePicker::make('date')->default(now())->required(),
                        ...$record->lines
                            ->filter(fn ($line): bool => $line->outstanding()->isGreaterThan(0))
                            ->map(fn ($line) => Forms\Components\TextInput::make('receive_'.$line->getKey())
                                ->label(((string) data_get($line, 'product.name', 'Item')).' (outstanding '.$line->outstanding().')')
                                ->numeric()->minValue(0)->default((string) $line->outstanding()))
                            ->values()->all(),
                    ])
                    ->action(function (PurchaseOrder $record, array $data): void {
                        $receipts = [];
                        foreach ($record->lines as $line) {
                            $value = $data['receive_'.$line->getKey()] ?? null;
                            if ($value !== null && (float) $value > 0) {
                                $receipts[$line->getKey()] = $value;
                            }
                        }
                        try {
                            app(ReceivePurchaseOrder::class)->handle($record, $receipts, (string) $data['date']);
                            Notification::make()->title('Goods received')->body('Stock and accounting updated.')->success()->send();
                        } catch (PurchaseOrderException|PostingException $e) {
                            Notification::make()->title('Cannot receive')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('print')->icon('heroicon-o-printer')
                    ->url(fn (PurchaseOrder $record): string => route('print.purchase-order', $record))->openUrlInNewTab(),
                Tables\Actions\Action::make('cancel')->icon('heroicon-o-x-mark')->color('danger')
                    ->visible(fn (PurchaseOrder $record): bool => in_array($record->status, [PurchaseOrderStatus::Draft, PurchaseOrderStatus::Approved], true))
                    ->requiresConfirmation()
                    ->action(fn (PurchaseOrder $record) => $record->update(['status' => PurchaseOrderStatus::Cancelled])),
            ])
            ->defaultSort('order_date', 'desc');
    }

    public static function getRelations(): array
    {
        return [LinesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPurchaseOrders::route('/'),
            'create' => Pages\CreatePurchaseOrder::route('/create'),
            'view' => Pages\ViewPurchaseOrder::route('/{record}'),
            'edit' => Pages\EditPurchaseOrder::route('/{record}/edit'),
        ];
    }
}
