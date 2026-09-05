<?php

namespace App\Filament\Resources;

use App\Actions\Export\AdvanceShipmentStatus;
use App\Enums\ExportShipmentStatus;
use App\Exceptions\ExportException;
use App\Filament\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\ExportShipmentResource\Pages;
use App\Models\ExportShipment;
use App\Support\CompanyContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Export Shipment (spec: Export). The physical consignment that ties the export
 * chain together — order, PI, LC, commercial invoice, packing list, delivery
 * order. Operational only: no ledger or stock impact of its own.
 */
class ExportShipmentResource extends Resource
{
    protected static ?string $model = ExportShipment::class;

    protected static ?string $navigationIcon = 'heroicon-o-globe-asia-australia';

    protected static ?string $navigationGroup = 'Export';

    protected static ?string $navigationLabel = 'Shipments';

    protected static ?int $navigationSort = 5;

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::query()
            ->whereIn('status', ['draft', 'ready_for_shipment', 'stuffing'])->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Shipment')->columns(2)->schema([
                Forms\Components\TextInput::make('number')->label('Shipment No.')->maxLength(32)
                    ->placeholder('Auto (SHP-0001) if left blank')
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('company_id', app(CompanyContext::class)->currentId())),
                Forms\Components\DatePicker::make('shipment_date')->default(now()),
                Forms\Components\Select::make('customer_id')->relationship('customer', 'name')->searchable()->preload()->required(),
                Forms\Components\Select::make('status')->options(ExportShipmentStatus::options())
                    ->default(ExportShipmentStatus::Draft->value)->required(),
            ]),
            Forms\Components\Section::make('Linked documents')->columns(3)->schema([
                Forms\Components\Select::make('sales_order_id')->label('Sales order')
                    ->relationship('salesOrder', 'so_number')->searchable()->preload(),
                Forms\Components\Select::make('proforma_invoice_id')->label('Proforma')
                    ->relationship('proformaInvoice', 'number')->searchable()->preload(),
                Forms\Components\Select::make('letter_of_credit_id')->label('Letter of credit')
                    ->relationship('letterOfCredit', 'number')->searchable()->preload(),
                Forms\Components\Select::make('commercial_invoice_id')->label('Commercial invoice')
                    ->relationship('commercialInvoice', 'number')->searchable()->preload(),
                Forms\Components\Select::make('delivery_order_id')->label('Delivery order')
                    ->relationship('deliveryOrder', 'number')->searchable()->preload(),
            ]),
            Forms\Components\Section::make('Logistics')->columns(3)->schema([
                Forms\Components\TextInput::make('port_of_loading')->maxLength(128),
                Forms\Components\TextInput::make('port_of_discharge')->maxLength(128),
                Forms\Components\TextInput::make('vessel_flight')->label('Vessel / flight')->maxLength(128),
                Forms\Components\TextInput::make('container_no')->label('Container no.')->maxLength(64),
                Forms\Components\TextInput::make('seal_no')->label('Seal no.')->maxLength(64),
                Forms\Components\TextInput::make('freight_forwarder')->maxLength(191),
                Forms\Components\TextInput::make('bl_awb_no')->label('BL / AWB no.')->maxLength(64),
            ]),
            Forms\Components\Textarea::make('notes')->rows(2)->maxLength(500)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')->label('Shipment No.')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('shipment_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('customer.name')->searchable(),
                Tables\Columns\TextColumn::make('commercialInvoice.number')->label('Invoice')->placeholder('—'),
                Tables\Columns\TextColumn::make('bl_awb_no')->label('BL/AWB')->placeholder('—'),
                Tables\Columns\TextColumn::make('container_no')->label('Container')->placeholder('—'),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (ExportShipmentStatus $state): string => $state->label())
                    ->color(fn (ExportShipmentStatus $state): string => $state->color()),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(ExportShipmentStatus::options()),
                Tables\Filters\SelectFilter::make('customer_id')->relationship('customer', 'name')->searchable()->preload(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('advance')->label('Update status')->icon('heroicon-o-arrow-right-circle')->color('info')
                    ->visible(fn (ExportShipment $record): bool => ! $record->status->isCancelled() && $record->status !== ExportShipmentStatus::Completed)
                    ->form(fn (ExportShipment $record): array => [
                        Forms\Components\Select::make('status')->label('New status')
                            ->options(collect(ExportShipmentStatus::cases())
                                ->filter(fn (ExportShipmentStatus $s): bool => $record->status->canAdvanceTo($s))
                                ->mapWithKeys(fn (ExportShipmentStatus $s): array => [$s->value => $s->label()])->all())
                            ->required(),
                    ])
                    ->action(function (ExportShipment $record, array $data): void {
                        try {
                            app(AdvanceShipmentStatus::class)->handle($record, ExportShipmentStatus::from($data['status']));
                            Notification::make()->title('Shipment updated')->success()->send();
                        } catch (ExportException $e) {
                            Notification::make()->title('Cannot update shipment')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->defaultSort('shipment_date', 'desc');
    }

    public static function getRelations(): array
    {
        return [DocumentsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExportShipments::route('/'),
            'create' => Pages\CreateExportShipment::route('/create'),
            'view' => Pages\ViewExportShipment::route('/{record}'),
            'edit' => Pages\EditExportShipment::route('/{record}/edit'),
        ];
    }
}
