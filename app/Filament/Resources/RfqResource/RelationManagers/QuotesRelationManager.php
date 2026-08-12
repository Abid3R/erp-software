<?php

namespace App\Filament\Resources\RfqResource\RelationManagers;

use App\Models\Rfq;
use App\Models\RfqLine;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
            Forms\Components\Select::make('supplier_id')->relationship('supplier', 'name')->searchable()->preload()->required(),
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
