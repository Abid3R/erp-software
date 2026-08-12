<?php

namespace App\Filament\Resources\SupplierPriceResource\Pages;

use App\Filament\Resources\SupplierPriceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSupplierPrices extends ListRecords
{
    protected static string $resource = SupplierPriceResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
