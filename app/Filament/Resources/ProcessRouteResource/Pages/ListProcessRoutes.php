<?php

namespace App\Filament\Resources\ProcessRouteResource\Pages;

use App\Filament\Resources\ProcessRouteResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProcessRoutes extends ListRecords
{
    protected static string $resource = ProcessRouteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
