<?php

namespace App\Filament\Resources\RfqResource\RelationManagers;

use App\Models\Rfq;
use App\Models\RfqLine;
use App\Models\RfqQuote;
use Closure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class QuotesRelationManager extends RelationManager
{
    protected static string $relationship = 'quotes';

    protected static ?string $title = 'Supplier quotes';

    public function form(Form $form): Form
    {
        /** @var Rfq $rfq */
        $rfq = $this->getOwnerRecord();

        return $form->schema([
            Forms\Components\Select::make('rfq_line_id')->label('Line')
                ->options($rfq->lines()->with('product')->get()
                    ->mapWithKeys(fn (RfqLine $l) => [$l->getKey() => ((string) data_get($l, 'product.name', '?')).' × '.$l->quantity])->all())
                ->required(),
            Forms\Components\Select::make('supplier_id')->relationship('supplier', 'name')->searchable()->preload()->required()
                // One quote per supplier per line — validate rather than hit the DB unique constraint.
                ->rule(fn (Forms\Get $get, ?Model $record): Closure => function (string $attribute, $value, Closure $fail) use ($get, $record): void {
                    if (blank($value) || blank($get('rfq_line_id'))) {
                        return;
                    }
                    $exists = RfqQuote::query()
                        ->where('rfq_line_id', $get('rfq_line_id'))
                        ->where('supplier_id', $value)
                        ->when($record, fn (Builder $q): Builder => $q->whereKeyNot($record->getKey()))
                        ->exists();
                    if ($exists) {
                        $fail('This supplier already has a quote for the selected line. Edit the existing quote instead.');
                    }
                }),
            Forms\Components\TextInput::make('unit_price')->numeric()->minValue(0)->required()->prefix(config('erp.currency.symbol')),
        ])->columns(3);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['rfqLine.product', 'supplier']))
            ->columns([
                Tables\Columns\TextColumn::make('rfqLine.product.name')->label('Product'),
                Tables\Columns\TextColumn::make('supplier.name')->label('Supplier'),
                Tables\Columns\TextColumn::make('unit_price')->money(config('erp.currency.code')),
            ])
            ->headerActions([Tables\Actions\CreateAction::make()->label('Add quote')])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }
}
