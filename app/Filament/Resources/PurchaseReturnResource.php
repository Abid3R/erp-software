<?php

namespace App\Filament\Resources;

use App\Actions\Purchasing\RecordPurchaseReturn;
use App\Enums\PurchaseReturnStatus;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\PostingException;
use App\Exceptions\PurchaseReturnException;
use App\Filament\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\PurchaseReturnResource\Pages;
use App\Models\PurchaseReturn;
use App\Support\CompanyContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PurchaseReturnResource extends Resource
{
    protected static ?string $model = PurchaseReturn::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-uturn-left';

    protected static ?string $navigationGroup = 'Purchasing';

    protected static ?string $navigationLabel = 'Purchase Returns';

    protected static ?int $navigationSort = 8;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('number')->required()->maxLength(32)
                ->default(fn (): string => 'PR-'.str_pad((string) (PurchaseReturn::query()->count() + 1), 4, '0', STR_PAD_LEFT))
                ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('company_id', app(CompanyContext::class)->currentId())),
            Forms\Components\Select::make('supplier_id')->relationship('supplier', 'name')->searchable()->preload()->required(),
            Forms\Components\Select::make('warehouse_id')->relationship('warehouse', 'name')->searchable()->preload()->required(),
            Forms\Components\DatePicker::make('return_date')->default(now())->required(),
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
                Tables\Columns\TextColumn::make('supplier.name')->searchable(),
                Tables\Columns\TextColumn::make('return_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('lines_count')->counts('lines')->label('Lines'),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (PurchaseReturnStatus $state): string => $state->label())
                    ->color(fn (PurchaseReturnStatus $state): string => $state->color()),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(PurchaseReturnStatus::options()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (PurchaseReturn $record): bool => $record->status === PurchaseReturnStatus::Draft),
                Tables\Actions\Action::make('post')->icon('heroicon-o-check-badge')->color('success')
                    ->visible(fn (PurchaseReturn $record): bool => $record->status === PurchaseReturnStatus::Draft)
                    ->requiresConfirmation()
                    ->modalDescription('Posting removes the returned stock and reverses the liability (Dr AP / Cr Inventory). It cannot be undone.')
                    ->action(function (PurchaseReturn $record): void {
                        try {
                            app(RecordPurchaseReturn::class)->handle($record);
                            Notification::make()->title('Purchase return posted')->body('Stock and accounting updated.')->success()->send();
                        } catch (PurchaseReturnException|InsufficientStockException|PostingException $e) {
                            Notification::make()->title('Cannot post return')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('cancel')->icon('heroicon-o-x-mark')->color('danger')
                    ->visible(fn (PurchaseReturn $record): bool => $record->status === PurchaseReturnStatus::Draft)
                    ->requiresConfirmation()
                    ->action(fn (PurchaseReturn $record) => $record->update(['status' => PurchaseReturnStatus::Cancelled])),
            ])
            ->defaultSort('return_date', 'desc');
    }

    public static function getRelations(): array
    {
        return [DocumentsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPurchaseReturns::route('/'),
            'create' => Pages\CreatePurchaseReturn::route('/create'),
            'view' => Pages\ViewPurchaseReturn::route('/{record}'),
            'edit' => Pages\EditPurchaseReturn::route('/{record}/edit'),
        ];
    }
}
