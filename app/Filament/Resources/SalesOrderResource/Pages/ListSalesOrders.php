<?php

namespace App\Filament\Resources\SalesOrderResource\Pages;

use App\Actions\Sales\ImportSalesOrderFromCsv;
use App\Filament\Resources\SalesOrderResource;
use App\Models\Customer;
use App\Models\Warehouse;
use App\Support\CompanyContext;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListSalesOrders extends ListRecords
{
    protected static string $resource = SalesOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('importCsv')
                ->label('Upload CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->modalHeading('Create a sales order from a CSV')
                ->modalDescription('Columns: Item Name, Item Code, Style / PO, Composition, Gsm, Width, Color / Code, QTY, Qty Unit, Unit Price. One line per row; a product is found/created per item code + style + colour.')
                ->form([
                    Forms\Components\Select::make('customer_id')->label('Customer')
                        ->options(fn (): array => Customer::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()->required(),
                    Forms\Components\Select::make('warehouse_id')->label('Warehouse')
                        ->options(fn (): array => Warehouse::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->default(fn () => Warehouse::query()->where('code', 'MAIN')->value('id') ?? Warehouse::query()->value('id'))
                        ->required(),
                    Forms\Components\DatePicker::make('order_date')->default(now())->required(),
                    Forms\Components\DatePicker::make('delivery_date'),
                    Forms\Components\FileUpload::make('csv')->label('Order CSV')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel', 'application/csv'])
                        ->storeFiles(false)->required()
                        ->helperText('.csv exported from the buyer order/booking sheet.'),
                ])
                ->action(function (array $data): void {
                    $file = is_array($data['csv']) ? reset($data['csv']) : $data['csv'];
                    $content = $file?->get();
                    if (! is_string($content) || trim($content) === '') {
                        Notification::make()->title('Empty file')->body('The uploaded CSV could not be read.')->danger()->send();

                        return;
                    }

                    try {
                        $order = app(ImportSalesOrderFromCsv::class)->handle($content, [
                            'customer_id' => (int) $data['customer_id'],
                            'warehouse_id' => (int) $data['warehouse_id'],
                            'order_date' => $data['order_date'] ?? null,
                            'delivery_date' => $data['delivery_date'] ?? null,
                        ]);
                    } catch (\Throwable $e) {
                        Notification::make()->title('Import failed')->body($e->getMessage())->danger()->send();

                        return;
                    }

                    Notification::make()->title('Sales order '.$order->so_number.' created')
                        ->body($order->lines()->count().' lines imported. Open it to review and confirm.')
                        ->success()
                        ->actions([
                            \Filament\Notifications\Actions\Action::make('open')->label('Open')
                                ->url(SalesOrderResource::getUrl('edit', ['record' => $order]))->button(),
                        ])
                        ->send();
                }),
        ];
    }
}
