<?php

namespace App\Filament\Resources\ExportShipmentResource\Pages;

use App\Filament\Resources\CommercialInvoiceResource;
use App\Filament\Resources\DeliveryOrderResource;
use App\Filament\Resources\ExportShipmentResource;
use App\Filament\Resources\LetterOfCreditResource;
use App\Filament\Resources\ProformaInvoiceResource;
use App\Models\ExportShipment;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewExportShipment extends ViewRecord
{
    protected static string $resource = ExportShipmentResource::class;

    protected function getHeaderActions(): array
    {
        /** @var ExportShipment $record */
        $record = $this->getRecord();

        return [
            Actions\Action::make('viewPi')->label('Proforma')->icon('heroicon-o-document-text')->color('gray')
                ->visible(fn (): bool => $record->proforma_invoice_id !== null)
                ->url(fn (): string => ProformaInvoiceResource::getUrl('view', ['record' => $record->proforma_invoice_id]))->openUrlInNewTab(),
            Actions\Action::make('viewLc')->label('Letter of credit')->icon('heroicon-o-banknotes')->color('gray')
                ->visible(fn (): bool => $record->letter_of_credit_id !== null)
                ->url(fn (): string => LetterOfCreditResource::getUrl('view', ['record' => $record->letter_of_credit_id]))->openUrlInNewTab(),
            Actions\Action::make('viewCi')->label('Commercial invoice')->icon('heroicon-o-document-currency-dollar')->color('gray')
                ->visible(fn (): bool => $record->commercial_invoice_id !== null)
                ->url(fn (): string => CommercialInvoiceResource::getUrl('view', ['record' => $record->commercial_invoice_id]))->openUrlInNewTab(),
            Actions\Action::make('viewDo')->label('Delivery order')->icon('heroicon-o-truck')->color('gray')
                ->visible(fn (): bool => $record->delivery_order_id !== null)
                ->url(fn (): string => DeliveryOrderResource::getUrl('view', ['record' => $record->delivery_order_id]))->openUrlInNewTab(),
            Actions\EditAction::make(),
        ];
    }
}
