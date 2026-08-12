<?php

namespace App\Filament\Resources;

use App\Actions\Sales\ConvertQuotationToOrder;
use App\Enums\QuotationStatus;
use App\Exceptions\QuotationException;
use App\Filament\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\QuotationResource\Pages;
use App\Models\Product;
use App\Models\Quotation;
use App\Support\CompanyContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class QuotationResource extends Resource
{
    protected static ?string $model = Quotation::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Order Processing';

    protected static ?string $navigationLabel = 'Quotations';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('number')->required()->maxLength(32)
                ->default(fn (): string => 'QT-'.str_pad((string) (Quotation::query()->count() + 1), 4, '0', STR_PAD_LEFT))
                ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('company_id', app(CompanyContext::class)->currentId())),
            Forms\Components\Select::make('customer_id')->relationship('customer', 'name')->searchable()->preload()->required(),
            Forms\Components\Select::make('warehouse_id')->relationship('warehouse', 'name')->searchable()->preload()->required(),
            Forms\Components\DatePicker::make('quote_date')->default(now())->required(),
            Forms\Components\DatePicker::make('valid_until'),
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
                Tables\Columns\TextColumn::make('quote_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('valid_until')->date()->sortable(),
                Tables\Columns\TextColumn::make('lines_count')->counts('lines')->label('Lines'),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (QuotationStatus $state): string => $state->label())
                    ->color(fn (QuotationStatus $state): string => $state->color()),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(QuotationStatus::options()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (Quotation $record): bool => in_array($record->status, [QuotationStatus::Draft, QuotationStatus::Sent], true)),
                Tables\Actions\Action::make('send')->icon('heroicon-o-paper-airplane')->color('info')
                    ->visible(fn (Quotation $record): bool => $record->status === QuotationStatus::Draft)
                    ->action(fn (Quotation $record) => $record->update(['status' => QuotationStatus::Sent])),
                Tables\Actions\Action::make('accept')->icon('heroicon-o-hand-thumb-up')->color('success')
                    ->visible(fn (Quotation $record): bool => $record->status === QuotationStatus::Sent)
                    ->action(fn (Quotation $record) => $record->update(['status' => QuotationStatus::Accepted])),
                Tables\Actions\Action::make('reject')->icon('heroicon-o-hand-thumb-down')->color('danger')
                    ->visible(fn (Quotation $record): bool => in_array($record->status, [QuotationStatus::Draft, QuotationStatus::Sent], true))
                    ->requiresConfirmation()
                    ->action(fn (Quotation $record) => $record->update(['status' => QuotationStatus::Rejected])),
                Tables\Actions\Action::make('convert')->icon('heroicon-o-arrow-right-circle')->color('success')
                    ->label('Convert to order')
                    ->visible(fn (Quotation $record): bool => $record->status->isConvertible())
                    ->requiresConfirmation()
                    ->modalDescription('Creates a confirmed sales order from this quotation. The quotation is then marked converted.')
                    ->action(function (Quotation $record): void {
                        try {
                            $order = app(ConvertQuotationToOrder::class)->handle($record);
                            Notification::make()->title('Sales order created')->body("Order {$order->so_number} created.")->success()->send();
                        } catch (QuotationException $e) {
                            Notification::make()->title('Cannot convert')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('print')->icon('heroicon-o-printer')->color('gray')
                    ->url(fn (Quotation $record): string => route('print.quotation', $record))->openUrlInNewTab(),
            ])
            ->defaultSort('quote_date', 'desc');
    }

    public static function getRelations(): array
    {
        return [DocumentsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQuotations::route('/'),
            'create' => Pages\CreateQuotation::route('/create'),
            'view' => Pages\ViewQuotation::route('/{record}'),
            'edit' => Pages\EditQuotation::route('/{record}/edit'),
        ];
    }
}
