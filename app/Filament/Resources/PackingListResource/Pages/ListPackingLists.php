<?php

namespace App\Filament\Resources\PackingListResource\Pages;

use App\Filament\Resources\PackingListResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPackingLists extends ListRecords
{
    protected static string $resource = PackingListResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
