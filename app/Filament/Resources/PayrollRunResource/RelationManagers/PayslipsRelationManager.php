<?php

namespace App\Filament\Resources\PayrollRunResource\RelationManagers;

use App\Models\Payslip;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PayslipsRelationManager extends RelationManager
{
    protected static string $relationship = 'payslips';

    protected static ?string $title = 'Payslips';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('basic')->numeric()->required()->prefix(config('erp.currency.symbol')),
            Forms\Components\Repeater::make('allowances')->schema([
                Forms\Components\TextInput::make('label')->required(),
                Forms\Components\TextInput::make('amount')->numeric()->required()->prefix(config('erp.currency.symbol')),
            ])->columns(2)->addActionLabel('Add allowance')->default([]),
            Forms\Components\Repeater::make('deductions')->schema([
                Forms\Components\TextInput::make('label')->required(),
                Forms\Components\TextInput::make('amount')->numeric()->required()->prefix(config('erp.currency.symbol')),
            ])->columns(2)->addActionLabel('Add deduction')->default([]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('employee'))
            ->columns([
                Tables\Columns\TextColumn::make('employee')->label('Employee')
                    ->state(fn (Payslip $record): string => $record->employee?->fullName() ?? '—'),
                Tables\Columns\TextColumn::make('basic')->money(config('erp.currency.code')),
                Tables\Columns\TextColumn::make('allowanceTotal')->label('Allowances')
                    ->state(fn (Payslip $record): string => $record->allowanceTotal())->money(config('erp.currency.code')),
                Tables\Columns\TextColumn::make('deductionTotal')->label('Deductions')
                    ->state(fn (Payslip $record): string => $record->deductionTotal())->money(config('erp.currency.code')),
                Tables\Columns\TextColumn::make('gross')->money(config('erp.currency.code')),
                Tables\Columns\TextColumn::make('net')->money(config('erp.currency.code'))->weight('bold'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('print')->icon('heroicon-o-printer')
                    ->url(fn (Payslip $record): string => route('print.payslip', $record))->openUrlInNewTab(),
            ]);
    }
}
