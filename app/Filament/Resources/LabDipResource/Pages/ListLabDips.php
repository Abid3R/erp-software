<?php

namespace App\Filament\Resources\LabDipResource\Pages;

use App\Filament\Resources\LabDipResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLabDips extends ListRecords
{
    protected static string $resource = LabDipResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
