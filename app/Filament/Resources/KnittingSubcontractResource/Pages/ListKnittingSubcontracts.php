<?php

namespace App\Filament\Resources\KnittingSubcontractResource\Pages;

use App\Filament\Resources\KnittingSubcontractResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListKnittingSubcontracts extends ListRecords
{
    protected static string $resource = KnittingSubcontractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
