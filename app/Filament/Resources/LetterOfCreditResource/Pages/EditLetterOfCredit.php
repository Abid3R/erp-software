<?php

namespace App\Filament\Resources\LetterOfCreditResource\Pages;

use App\Filament\Resources\LetterOfCreditResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLetterOfCredit extends EditRecord
{
    protected static string $resource = LetterOfCreditResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\ViewAction::make()];
    }
}
