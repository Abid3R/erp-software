<?php

namespace App\Filament\Resources;

use App\Enums\PackingListStatus;
use App\Filament\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\PackingListResource\Pages;
use App\Models\PackingList;
use App\Support\CompanyContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Packing List (spec: Export). Documentary record of how a consignment is packed —
 * cartons/rolls, weights, marks & numbers. Generated from a Commercial Invoice so
 * quantities are not re-keyed. No ledger or stock impact.
 */
class PackingListResource extends Resource
{
    protected static ?string $model = PackingList::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Export';

    protected static ?string $navigationLabel = 'Packing Lists';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Packing list')->columns(2)->schema([
                Forms\Components\TextInput::make('number')->label('PL No.')->maxLength(32)
                    ->placeholder('Auto (PL-0001) if left blank')
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('company_id', app(CompanyContext::class)->currentId())),
                Forms\Components\DatePicker::make('pl_date')->default(now())->required(),
                Forms\Components\Select::make('customer_id')->relationship('customer', 'name')->searchable()->preload()->required(),
                Forms\Components\Select::make('commercial_invoice_id')->label('Commercial invoice')
                    ->relationship('commercialInvoice', 'number')->searchable()->preload(),
                Forms\Components\Select::make('export_shipment_id')->label('Shipment')
                    ->relationship('shipment', 'number')->searchable()->preload(),
                Forms\Components\Select::make('delivery_order_id')->label('Delivery order')
                    ->relationship('deliveryOrder', 'number')->searchable()->preload(),
                Forms\Components\TextInput::make('total_packages')->numeric()->minValue(0),
                Forms\Components\TextInput::make('marks_numbers')->label('Marks & numbers')->maxLength(191),
            ]),
            Forms\Components\Section::make('Packages')->schema([
                Forms\Components\Repeater::make('lines')->relationship()->schema([
                    Forms\Components\Select::make('product_id')->label('Product')
                        ->relationship('product', 'name')->searchable()->preload()->required(),
                    Forms\Components\TextInput::make('carton_no')->label('Carton/Roll #')->maxLength(64),
                    Forms\Components\TextInput::make('quantity')->numeric()->minValue(0),
                    Forms\Components\TextInput::make('net_weight')->numeric()->minValue(0),
                    Forms\Components\TextInput::make('gross_weight')->numeric()->minValue(0),
                    Forms\Components\TextInput::make('dimensions')->maxLength(64),
                ])->columns(6)->minItems(1)->addActionLabel('Add package')->columnSpanFull(),
            ]),
            Forms\Components\Textarea::make('notes')->rows(2)->maxLength(500)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')->label('PL No.')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('pl_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('customer.name')->searchable(),
                Tables\Columns\TextColumn::make('commercialInvoice.number')->label('Invoice')->placeholder('—'),
                Tables\Columns\TextColumn::make('shipment.number')->label('Shipment')->placeholder('—'),
                Tables\Columns\TextColumn::make('total_packages')->label('Pkgs')->placeholder('—'),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (PackingListStatus $state): string => $state->label())
                    ->color(fn (PackingListStatus $state): string => $state->color()),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(PackingListStatus::options()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (PackingList $record): bool => $record->status === PackingListStatus::Draft),
                Tables\Actions\Action::make('confirm')->label('Confirm')->icon('heroicon-o-check')->color('success')
                    ->visible(fn (PackingList $record): bool => $record->status === PackingListStatus::Draft)
                    ->requiresConfirmation()
                    ->action(function (PackingList $record): void {
                        $record->update(['status' => PackingListStatus::Confirmed]);
                        Notification::make()->title('Packing list confirmed')->success()->send();
                    }),
                Tables\Actions\Action::make('print')->icon('heroicon-o-printer')->color('gray')
                    ->url(fn (PackingList $record): string => route('print.packing-list', $record))->openUrlInNewTab(),
            ])
            ->defaultSort('pl_date', 'desc');
    }

    public static function getRelations(): array
    {
        return [DocumentsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPackingLists::route('/'),
            'create' => Pages\CreatePackingList::route('/create'),
            'view' => Pages\ViewPackingList::route('/{record}'),
            'edit' => Pages\EditPackingList::route('/{record}/edit'),
        ];
    }
}
