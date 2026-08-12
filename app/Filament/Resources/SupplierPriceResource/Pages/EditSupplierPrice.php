<?php

namespace App\Filament\Resources\SupplierPriceResource\Pages;

use App\Filament\Resources\SupplierPriceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSupplierPrice extends EditRecord
{
    protected static string $resource = SupplierPriceResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
