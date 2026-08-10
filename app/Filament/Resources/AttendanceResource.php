<?php

namespace App\Filament\Resources;

use App\Enums\AttendanceStatus;
use App\Filament\Resources\AttendanceResource\Pages;
use App\Models\Attendance;
use App\Models\Employee;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AttendanceResource extends Resource
{
    protected static ?string $model = Attendance::class;

    protected static ?string $navigationIcon = 'heroicon-o-finger-print';

    protected static ?string $navigationGroup = 'HR';

    protected static ?int $navigationSort = 5;

    /** "Hh Mm" from a minute count. */
    private static function hoursMinutes(int $minutes): string
    {
        return intdiv($minutes, 60).'h '.($minutes % 60).'m';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('employee_id')->relationship('employee', 'first_name')
                ->getOptionLabelFromRecordUsing(fn (Employee $r): string => $r->employee_code.' — '.$r->fullName())
                ->searchable()->preload()->required(),
            Forms\Components\DatePicker::make('date')->required()->default(now()),
            Forms\Components\Select::make('shift_id')->relationship('shift', 'name')->searchable()->preload(),
            Forms\Components\Select::make('status')->options(AttendanceStatus::options())->default('present')->required(),
            Forms\Components\TimePicker::make('check_in')->seconds(false),
            Forms\Components\TimePicker::make('check_out')->seconds(false),
            Forms\Components\TextInput::make('remarks')->maxLength(255)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('date')->date()->sortable(),
                Tables\Columns\TextColumn::make('employee.first_name')->label('Employee')
                    ->state(fn (Attendance $r): string => $r->employee?->fullName() ?? '—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('shift.name')->placeholder('—'),
                Tables\Columns\TextColumn::make('check_in')->time('H:i')->placeholder('—'),
                Tables\Columns\TextColumn::make('check_out')->time('H:i')->placeholder('—'),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (AttendanceStatus $s): string => $s->label())
                    ->color(fn (AttendanceStatus $s): string => $s->color()),
                Tables\Columns\TextColumn::make('worked_minutes')->label('Worked')
                    ->formatStateUsing(fn (int $state): string => self::hoursMinutes($state)),
                Tables\Columns\TextColumn::make('late_minutes')->label('Late')->suffix(' m')->toggleable(),
                Tables\Columns\TextColumn::make('overtime_minutes')->label('OT')
                    ->formatStateUsing(fn (int $state): string => self::hoursMinutes($state))->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(AttendanceStatus::options()),
                Tables\Filters\SelectFilter::make('employee')->relationship('employee', 'first_name'),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->defaultSort('date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAttendances::route('/'),
            'create' => Pages\CreateAttendance::route('/create'),
            'edit' => Pages\EditAttendance::route('/{record}/edit'),
        ];
    }
}
