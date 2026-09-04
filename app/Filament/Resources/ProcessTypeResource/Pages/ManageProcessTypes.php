<?php

namespace App\Filament\Resources\ProcessTypeResource\Pages;

use App\Filament\Resources\ProcessTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageProcessTypes extends ManageRecords
{
    protected static string $resource = ProcessTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
