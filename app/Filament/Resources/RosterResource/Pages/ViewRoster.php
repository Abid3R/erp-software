<?php

namespace App\Filament\Resources\RosterResource\Pages;

use App\Filament\Resources\RosterResource;
use App\Models\Roster;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewRoster extends ViewRecord
{
    protected static string $resource = RosterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('print')->icon('heroicon-o-printer')
                ->url(fn (): string => route('print.roster', $this->record))->openUrlInNewTab(),
            Actions\DeleteAction::make(),
        ];
    }

    public function getTitle(): string
    {
        /** @var Roster $roster */
        $roster = $this->record;

        return $roster->name;
    }
}
