<?php

namespace App\Filament\Resources\BatchResource\Pages;

use App\Domain\Manufacturing\BatchTrace;
use App\Filament\Resources\BatchResource;
use Filament\Resources\Pages\ViewRecord;

class ViewBatch extends ViewRecord
{
    protected static string $resource = BatchResource::class;

    protected static string $view = 'filament.resources.batch.trace';

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $batch = $this->getRecord();

        return [
            'batch' => $batch,
            'sourceLabel' => BatchResource::sourceLabel($batch),
            'origin' => BatchTrace::origin($batch),
            'usage' => BatchTrace::usage($batch),
        ];
    }
}
