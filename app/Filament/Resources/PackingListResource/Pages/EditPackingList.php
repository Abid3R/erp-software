<?php

namespace App\Filament\Resources\PackingListResource\Pages;

use App\Filament\Resources\PackingListResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPackingList extends EditRecord
{
    protected static string $resource = PackingListResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\ViewAction::make()];
    }
}
