<?php

namespace App\Filament\Resources\KnittingSubcontractResource\Pages;

use App\Enums\ProcessMode;
use App\Filament\Resources\KnittingSubcontractResource;
use Filament\Resources\Pages\CreateRecord;

class CreateKnittingSubcontract extends CreateRecord
{
    protected static string $resource = KnittingSubcontractResource::class;

    /** Force sub-contract mode regardless of the hidden field. */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['mode'] = ProcessMode::Subcontract->value;

        return $data;
    }
}
