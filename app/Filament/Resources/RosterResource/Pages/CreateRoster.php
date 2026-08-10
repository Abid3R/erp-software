<?php

namespace App\Filament\Resources\RosterResource\Pages;

use App\Actions\Hr\GenerateRoster;
use App\Filament\Resources\RosterResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateRoster extends CreateRecord
{
    protected static string $resource = RosterResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        return app(GenerateRoster::class)->handle(
            (string) $data['name'],
            (string) $data['start_date'],
            (string) $data['end_date'],
            array_map('intval', $data['employees'] ?? []),
            array_map('intval', $data['shifts'] ?? []),
            (int) ($data['off_days_per_week'] ?? 1),
            (int) ($data['max_hours_per_week'] ?? 48),
        );
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
