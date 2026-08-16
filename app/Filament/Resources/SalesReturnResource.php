<?php

namespace App\Filament\Resources;

use App\Actions\Sales\PostSalesReturn;
use App\Enums\SalesReturnStatus;
use App\Exceptions\PostingException;
use App\Exceptions\SalesReturnException;
use App\Filament\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\SalesReturnResource\Pages;
use App\Models\Product;
use App\Models\SalesReturn;
use App\Support\CompanyContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SalesReturnResource extends Resource
{
    protected static ?string $model = SalesReturn::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-uturn-left';

    protected static ?string $navigationGroup = 'Sales';

    protected static ?string $navigationLabel = 'Sales Returns';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('number')->required()->maxLength(32)
                ->default(fn (): string => 'SR-'.str_pad((string) (SalesReturn::query()->count() + 1), 4, '0', STR_PAD_LEFT))
                ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('company_id', app(CompanyContext::class)->currentId())),
            Forms\Components\Select::make('customer_id')->relationship('customer', 'name')->searchable()->preload()->required(),
            Forms\Components\Select::make('warehouse_id')->relationship('warehouse', 'name')->searchable()->preload()->required(),
            Forms\Components\DatePicker::make('return_date')->default(now())->required(),
            Forms\Components\TextInput::make('notes')->maxLength(255)->columnSpanFull(),
            Forms\Components\Repeater::make('lines')->relationship()->schema([
                Forms\Components\Select::make('product_id')->label('Product')->relationship('product', 'name')->searchable()->preload()->required()
                    ->live()->afterStateUpdated(function ($state, Forms\Set $set): void {
                        $price = $state ? Product::find($state)?->selling_price : null;
                        if ($price !== null) {
                            $set('unit_price', (string) $price);
                        }
                    }),
                Forms\Components\TextInput::make('quantity')->numeric()->minValue(0.0001)->required(),
                Forms\Components\TextInput::make('unit_price')->numeric()->minValue(0)->required(),
            ])->columns(3)->minItems(1)->addActionLabel('Add line')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('customer.name')->searchable(),
                Tables\Columns\TextColumn::make('return_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('lines_count')->counts('lines')->label('Lines'),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (SalesReturnStatus $state): string => $state->label())
                    ->color(fn (SalesReturnStatus $state): string => $state->color()),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(SalesReturnStatus::options()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (SalesReturn $record): bool => $record->status === SalesReturnStatus::Draft),
                Tables\Actions\Action::make('post')->icon('heroicon-o-check-badge')->color('success')
                    ->visible(fn (SalesReturn $record): bool => $record->status === SalesReturnStatus::Draft)
                    ->requiresConfirmation()
                    ->modalDescription('Posting returns the stock (at cost) and reverses revenue and COGS. It cannot be undone.')
                    ->action(function (SalesReturn $record): void {
                        try {
                            app(PostSalesReturn::class)->handle($record);
                            Notification::make()->title('Sales return posted')->body('Stock and accounting updated.')->success()->send();
                        } catch (SalesReturnException|PostingException $e) {
                            Notification::make()->title('Cannot post return')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('cancel')->icon('heroicon-o-x-mark')->color('danger')
                    ->visible(fn (SalesReturn $record): bool => $record->status === SalesReturnStatus::Draft)
                    ->requiresConfirmation()
                    ->action(fn (SalesReturn $record) => $record->update(['status' => SalesReturnStatus::Cancelled])),
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
            'index' => Pages\ListSalesReturns::route('/'),
            'create' => Pages\CreateSalesReturn::route('/create'),
            'view' => Pages\ViewSalesReturn::route('/{record}'),
            'edit' => Pages\EditSalesReturn::route('/{record}/edit'),
        ];
    }
}
