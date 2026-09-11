<?php

namespace App\Filament\Resources\DyeingSpecificationResource\Pages;

use App\Filament\Resources\DyeingSpecificationResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageDyeingSpecifications extends ManageRecords
{
    protected static string $resource = DyeingSpecificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
