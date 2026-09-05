<?php

namespace App\Filament\Resources\LetterOfCreditResource\Pages;

use App\Filament\Resources\LetterOfCreditResource;
use App\Models\LetterOfCredit;
use Filament\Actions;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewLetterOfCredit extends ViewRecord
{
    protected static string $resource = LetterOfCreditResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\EditAction::make()];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Utilisation')->columns(4)->schema([
                TextEntry::make('number')->label('LC No.'),
                TextEntry::make('customer.name')->label('Applicant'),
                TextEntry::make('amount')->label('LC value')
                    ->formatStateUsing(fn (LetterOfCredit $record): string => $record->currency_code.' '.number_format((float) $record->amount, 2)),
                TextEntry::make('status')->badge()
                    ->formatStateUsing(fn ($state): string => $state->label())
                    ->color(fn ($state): string => $state->color()),
                TextEntry::make('allocated')->label('Allocated (PIs)')
                    ->state(fn (LetterOfCredit $record): string => $record->currency_code.' '.number_format((float) (string) $record->allocated(), 2))
                    ->color('info'),
                TextEntry::make('remaining')->label('Remaining')
                    ->state(fn (LetterOfCredit $record): string => $record->currency_code.' '.number_format((float) (string) $record->remaining(), 2))
                    ->color(fn (LetterOfCredit $record): string => $record->remaining()->isNegative() ? 'danger' : 'success'),
                TextEntry::make('expiry_date')->date()
                    ->color(fn (LetterOfCredit $record): string => $record->isPastExpiry() ? 'danger' : 'gray'),
                TextEntry::make('latest_shipment_date')->date()->placeholder('—'),
            ]),
            Section::make('Banking')->columns(3)->schema([
                TextEntry::make('issuing_bank')->placeholder('—'),
                TextEntry::make('advising_bank')->placeholder('—'),
                TextEntry::make('beneficiary')->placeholder('—'),
                TextEntry::make('payment_terms')->placeholder('—'),
                TextEntry::make('port_of_loading')->placeholder('—'),
                TextEntry::make('port_of_discharge')->placeholder('—'),
            ]),
        ]);
    }
}
