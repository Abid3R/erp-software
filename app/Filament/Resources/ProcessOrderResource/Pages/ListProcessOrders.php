<?php

namespace App\Filament\Resources\ProcessOrderResource\Pages;

use App\Filament\Resources\ProcessOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProcessOrders extends ListRecords
{
    protected static string $resource = ProcessOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
