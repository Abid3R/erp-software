<?php

namespace App\Filament\Resources\RosterResource\RelationManagers;

use App\Models\RosterEntry;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'entries';

    protected static ?string $title = 'Roster entries';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['employee', 'shift']))
            ->columns([
                Tables\Columns\TextColumn::make('date')->date()->sortable(),
                Tables\Columns\TextColumn::make('employee')->label('Employee')
                    ->state(fn (RosterEntry $record): string => $record->employee?->fullName() ?? '—'),
                Tables\Columns\TextColumn::make('shift.name')->label('Shift')->badge()
                    ->placeholder('OFF')
                    ->color(fn (RosterEntry $record): string => $record->is_off ? 'gray' : 'primary'),
                Tables\Columns\TextColumn::make('note')->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('employee_id')->relationship('employee', 'first_name')->label('Employee'),
            ])
            ->defaultSort('date')
            ->paginated([25, 50, 100]);
    }
}
