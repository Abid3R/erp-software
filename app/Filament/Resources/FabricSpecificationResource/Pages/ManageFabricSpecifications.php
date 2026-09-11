<?php

namespace App\Filament\Resources\FabricSpecificationResource\Pages;

use App\Filament\Resources\FabricSpecificationResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageFabricSpecifications extends ManageRecords
{
    protected static string $resource = FabricSpecificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
