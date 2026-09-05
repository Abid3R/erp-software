<?php

namespace App\Filament\Resources\LetterOfCreditResource\RelationManagers;

use App\Filament\Resources\ProformaInvoiceResource;
use App\Models\ProformaInvoice;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Proforma Invoices allocated to this Letter of Credit. Read-only listing —
 * allocation itself happens from the PI (Allocate to LC action) so the
 * over-allocation guard runs in one place.
 */
class ProformaInvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'proformaInvoices';

    protected static ?string $title = 'Allocated proforma invoices';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['customer', 'lines', 'taxRate']))
            ->columns([
                Tables\Columns\TextColumn::make('number')->label('PI No.')->searchable(),
                Tables\Columns\TextColumn::make('pi_date')->date(),
                Tables\Columns\TextColumn::make('customer.name'),
                Tables\Columns\TextColumn::make('currency_code')->label('Ccy'),
                Tables\Columns\TextColumn::make('total')->label('PI total')
                    ->state(fn (ProformaInvoice $record): string => number_format((float) (string) $record->total(), 2)),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->formatStateUsing(fn ($state): string => $state->label())
                    ->color(fn ($state): string => $state->color()),
            ])
            ->actions([
                Tables\Actions\Action::make('open')->label('Open')->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (ProformaInvoice $record): string => ProformaInvoiceResource::getUrl('view', ['record' => $record]))
                    ->openUrlInNewTab(),
            ]);
    }
}
