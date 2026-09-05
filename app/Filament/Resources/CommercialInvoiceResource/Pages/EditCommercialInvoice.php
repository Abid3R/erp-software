<?php

namespace App\Filament\Resources\CommercialInvoiceResource\Pages;

use App\Filament\Resources\CommercialInvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCommercialInvoice extends EditRecord
{
    protected static string $resource = CommercialInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\ViewAction::make()];
    }
}
