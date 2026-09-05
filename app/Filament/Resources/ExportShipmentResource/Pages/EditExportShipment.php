<?php

namespace App\Filament\Resources\ExportShipmentResource\Pages;

use App\Filament\Resources\ExportShipmentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditExportShipment extends EditRecord
{
    protected static string $resource = ExportShipmentResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\ViewAction::make()];
    }
}
