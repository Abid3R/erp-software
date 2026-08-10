<?php

namespace App\Filament\Resources\PayrollRunResource\Pages;

use App\Actions\Hr\GeneratePayroll;
use App\Filament\Resources\PayrollRunResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePayrollRun extends CreateRecord
{
    protected static string $resource = PayrollRunResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        return app(GeneratePayroll::class)->handle(
            (string) $data['name'],
            (int) $data['year'],
            (int) $data['month'],
            array_map('intval', $data['employees'] ?? []),
        );
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
