<?php

namespace App\Filament\Resources\PackingListResource\Pages;

use App\Filament\Resources\PackingListResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPackingList extends ViewRecord
{
    protected static string $resource = PackingListResource::class;

    protected function getHeaderActions(): array
    {
        /** @var \App\Models\PackingList $record */
        $record = $this->getRecord();

        return [
            Actions\Action::make('print')->label('Print')->icon('heroicon-o-printer')->color('gray')
                ->url(fn (): string => route('print.packing-list', $record))->openUrlInNewTab(),
            Actions\EditAction::make(),
        ];
    }
}
