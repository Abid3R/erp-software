<?php

namespace App\Filament\Resources\ProformaInvoiceResource\Pages;

use App\Filament\Resources\LetterOfCreditResource;
use App\Filament\Resources\ProformaInvoiceResource;
use App\Filament\Resources\SalesOrderResource;
use App\Models\ProformaInvoice;
use Filament\Actions;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewProformaInvoice extends ViewRecord
{
    protected static string $resource = ProformaInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        /** @var ProformaInvoice $record */
        $record = $this->getRecord();

        return [
            Actions\Action::make('viewSalesOrder')->label('Sales order')->icon('heroicon-o-shopping-cart')->color('gray')
                ->visible(fn (): bool => $record->sales_order_id !== null)
                ->url(fn (): string => SalesOrderResource::getUrl('view', ['record' => $record->sales_order_id]))->openUrlInNewTab(),
            Actions\Action::make('viewLc')->label('Letter of credit')->icon('heroicon-o-banknotes')->color('gray')
                ->visible(fn (): bool => $record->letter_of_credit_id !== null)
                ->url(fn (): string => LetterOfCreditResource::getUrl('view', ['record' => $record->letter_of_credit_id]))->openUrlInNewTab(),
            Actions\Action::make('print')->label('Print')->icon('heroicon-o-printer')->color('gray')
                ->url(fn (): string => route('print.proforma-invoice', $record))->openUrlInNewTab(),
            Actions\EditAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Proforma invoice')->columns(4)->schema([
                TextEntry::make('number')->label('PI No.'),
                TextEntry::make('pi_date')->date(),
                TextEntry::make('customer.name'),
                TextEntry::make('status')->badge()
                    ->formatStateUsing(fn ($state): string => $state->label())->color(fn ($state): string => $state->color()),
                TextEntry::make('currency_code')->label('Currency'),
                TextEntry::make('incoterm')->placeholder('—'),
                TextEntry::make('payment_terms')->placeholder('—'),
                TextEntry::make('letterOfCredit.number')->label('LC')->placeholder('—'),
            ]),
            Section::make('Items')->schema([
                RepeatableEntry::make('lines')->schema([
                    TextEntry::make('product.name')->label('Product'),
                    TextEntry::make('quantity'),
                    TextEntry::make('unit_price')->label('Unit price'),
                    TextEntry::make('amount')->label('Amount')
                        ->state(fn ($record): string => number_format((float) (string) $record->amount(), 2)),
                ])->columns(4),
            ]),
            Section::make('Totals')->columns(4)->schema([
                TextEntry::make('subtotal')->state(fn (ProformaInvoice $record): string => number_format((float) (string) $record->subtotal(), 2)),
                TextEntry::make('discount')->label('Discount')->state(fn (ProformaInvoice $record): string => number_format((float) (string) $record->discountAmount(), 2)),
                TextEntry::make('tax')->label('Tax / VAT')->state(fn (ProformaInvoice $record): string => number_format((float) (string) $record->taxAmount(), 2)),
                TextEntry::make('total')->label('Total')->weight('bold')
                    ->state(fn (ProformaInvoice $record): string => $record->currency_code.' '.number_format((float) (string) $record->total(), 2)),
            ]),
        ]);
    }
}
