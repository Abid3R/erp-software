<?php

namespace App\Filament\Resources\CommercialInvoiceResource\Pages;

use App\Filament\Resources\CommercialInvoiceResource;
use App\Filament\Resources\DeliveryOrderResource;
use App\Filament\Resources\LetterOfCreditResource;
use App\Filament\Resources\ProformaInvoiceResource;
use App\Models\CommercialInvoice;
use Filament\Actions;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewCommercialInvoice extends ViewRecord
{
    protected static string $resource = CommercialInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        /** @var CommercialInvoice $record */
        $record = $this->getRecord();

        return [
            Actions\Action::make('viewPi')->label('Proforma')->icon('heroicon-o-document-text')->color('gray')
                ->visible(fn (): bool => $record->proforma_invoice_id !== null)
                ->url(fn (): string => ProformaInvoiceResource::getUrl('view', ['record' => $record->proforma_invoice_id]))->openUrlInNewTab(),
            Actions\Action::make('viewLc')->label('Letter of credit')->icon('heroicon-o-banknotes')->color('gray')
                ->visible(fn (): bool => $record->letter_of_credit_id !== null)
                ->url(fn (): string => LetterOfCreditResource::getUrl('view', ['record' => $record->letter_of_credit_id]))->openUrlInNewTab(),
            Actions\Action::make('viewDo')->label('Delivery order')->icon('heroicon-o-truck')->color('gray')
                ->visible(fn (): bool => $record->delivery_order_id !== null)
                ->url(fn (): string => DeliveryOrderResource::getUrl('view', ['record' => $record->delivery_order_id]))->openUrlInNewTab(),
            Actions\Action::make('viewJournal')->label('Journal')->icon('heroicon-o-book-open')->color('gray')
                ->visible(fn (): bool => $record->journal_id !== null)
                ->url(fn (): string => route('print.journal', $record->journal_id))->openUrlInNewTab(),
            Actions\Action::make('print')->label('Print')->icon('heroicon-o-printer')->color('gray')
                ->url(fn (): string => route('print.commercial-invoice', $record))->openUrlInNewTab(),
            Actions\EditAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Commercial invoice')->columns(4)->schema([
                TextEntry::make('number')->label('Invoice No.'),
                TextEntry::make('invoice_date')->date(),
                TextEntry::make('customer.name'),
                TextEntry::make('status')->badge()
                    ->formatStateUsing(fn ($state): string => $state->label())->color(fn ($state): string => $state->color()),
                TextEntry::make('currency_code')->label('Currency'),
                TextEntry::make('exchange_rate')->label('Rate → base'),
                TextEntry::make('incoterm')->placeholder('—'),
                TextEntry::make('country_of_origin')->placeholder('—'),
            ]),
            Section::make('Items')->schema([
                RepeatableEntry::make('lines')->schema([
                    TextEntry::make('product.name')->label('Product'),
                    TextEntry::make('hs_code')->label('HS code')->placeholder('—'),
                    TextEntry::make('quantity'),
                    TextEntry::make('unit_price')->label('Unit price'),
                    TextEntry::make('amount')->label('Amount')
                        ->state(fn ($record): string => number_format((float) (string) $record->amount(), 2)),
                ])->columns(5),
            ]),
            Section::make('Totals')->columns(4)->schema([
                TextEntry::make('subtotal')->state(fn (CommercialInvoice $record): string => number_format((float) (string) $record->subtotal(), 2)),
                TextEntry::make('discount')->label('Discount')->state(fn (CommercialInvoice $record): string => number_format((float) (string) $record->discountAmount(), 2)),
                TextEntry::make('tax')->label('Tax / VAT')->state(fn (CommercialInvoice $record): string => number_format((float) (string) $record->taxAmount(), 2)),
                TextEntry::make('total')->label('Total')->weight('bold')
                    ->state(fn (CommercialInvoice $record): string => $record->currency_code.' '.number_format((float) (string) $record->total(), 2)),
            ]),
        ]);
    }
}
