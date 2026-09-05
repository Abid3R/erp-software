<?php

namespace App\Filament\Resources\CommercialInvoiceResource\Pages;

use App\Filament\Resources\CommercialInvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCommercialInvoices extends ListRecords
{
    protected static string $resource = CommercialInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
