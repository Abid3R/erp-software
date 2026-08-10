<?php

namespace App\Filament\Resources;

use App\Enums\RosterStatus;
use App\Filament\Resources\RosterResource\Pages;
use App\Filament\Resources\RosterResource\RelationManagers\EntriesRelationManager;
use App\Models\Employee;
use App\Models\Roster;
use App\Models\Shift;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Auto-rostering (spec #24). The create form captures generator parameters; the
 * roster + entries are produced by GenerateRoster and reviewed on the view page.
 */
class RosterResource extends Resource
{
    protected static ?string $model = Roster::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'HR';

    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required()->maxLength(255)
                ->default('Roster '.now()->format('M Y')),
            Forms\Components\DatePicker::make('start_date')->required()->default(now()->startOfWeek()),
            Forms\Components\DatePicker::make('end_date')->required()->default(now()->startOfWeek()->addDays(6)),
            Forms\Components\Select::make('employees')->multiple()->required()
                ->options(fn (): array => Employee::query()->where('status', 'active')->get()
                    ->mapWithKeys(fn (Employee $e) => [$e->getKey() => $e->employee_code.' — '.$e->fullName()])->all())
                ->searchable()->helperText('Only active employees can be rostered.'),
            Forms\Components\Select::make('shifts')->multiple()->required()
                ->options(fn (): array => Shift::query()->where('is_active', true)->pluck('name', 'id')->all())
                ->helperText('Shifts rotate across the team.'),
            Forms\Components\TextInput::make('off_days_per_week')->numeric()->default(1)->minValue(0)->maxValue(6)->required(),
            Forms\Components\TextInput::make('max_hours_per_week')->numeric()->default(48)->minValue(1)->required()->suffix('hrs'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('start_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('end_date')->date(),
                Tables\Columns\TextColumn::make('entries_count')->counts('entries')->label('Entries'),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (RosterStatus $s): string => $s->label())
                    ->color(fn (RosterStatus $s): string => $s->color()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('print')->icon('heroicon-o-printer')
                    ->url(fn (Roster $record): string => route('print.roster', $record))->openUrlInNewTab(),
                Tables\Actions\Action::make('publish')->icon('heroicon-o-check')->color('success')
                    ->visible(fn (Roster $record): bool => $record->status === RosterStatus::Draft)
                    ->requiresConfirmation()
                    ->action(function (Roster $record): void {
                        $record->update(['status' => RosterStatus::Published]);
                        Notification::make()->title('Roster published')->success()->send();
                    }),
            ])
            ->defaultSort('start_date', 'desc');
    }

    public static function getRelations(): array
    {
        return [EntriesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRosters::route('/'),
            'create' => Pages\CreateRoster::route('/create'),
            'view' => Pages\ViewRoster::route('/{record}'),
        ];
    }
}
