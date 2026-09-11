<?php

namespace App\Filament\Resources\ProcessRouteResource\Pages;

use App\Filament\Resources\ProcessRouteResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProcessRoute extends EditRecord
{
    protected static string $resource = ProcessRouteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
