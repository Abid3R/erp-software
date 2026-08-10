<?php

namespace App\Filament\Resources;

use App\Enums\EmployeeStatus;
use App\Enums\EmploymentType;
use App\Enums\Gender;
use App\Filament\Resources\EmployeeResource\Pages;
use App\Models\Employee;
use App\Support\CompanyContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'HR';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identity')->schema([
                Forms\Components\TextInput::make('employee_code')->required()->maxLength(32)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('company_id', app(CompanyContext::class)->currentId())),
                Forms\Components\TextInput::make('first_name')->required()->maxLength(255),
                Forms\Components\TextInput::make('last_name')->maxLength(255),
                Forms\Components\TextInput::make('email')->email()->maxLength(255),
                Forms\Components\TextInput::make('phone')->tel()->maxLength(32),
            ])->columns(2),

            Forms\Components\Section::make('Employment')->schema([
                Forms\Components\Select::make('department_id')->relationship('department', 'name')->searchable()->preload(),
                Forms\Components\Select::make('designation_id')->relationship('designation', 'title')->searchable()->preload(),
                Forms\Components\Select::make('manager_id')->relationship('manager', 'first_name')
                    ->getOptionLabelFromRecordUsing(fn (Employee $record): string => $record->fullName())
                    ->searchable()->preload()->label('Reports to'),
                Forms\Components\Select::make('employment_type')->options(EmploymentType::options())->default('permanent')->required(),
                Forms\Components\Select::make('status')->options(EmployeeStatus::options())->default('active')->required(),
                Forms\Components\DatePicker::make('join_date')->required()->default(now()),
                Forms\Components\DatePicker::make('termination_date'),
                Forms\Components\TextInput::make('base_salary')->numeric()->default(0)
                    ->prefix(config('erp.currency.symbol')),
            ])->columns(2),

            Forms\Components\Section::make('Personal')->schema([
                Forms\Components\DatePicker::make('date_of_birth'),
                Forms\Components\Select::make('gender')->options(Gender::options()),
                Forms\Components\TextInput::make('address')->maxLength(255)->columnSpanFull(),
            ])->columns(2)->collapsed(),

            Forms\Components\Section::make('System access')->schema([
                Forms\Components\Select::make('user_id')->relationship('user', 'name')->searchable()->preload()
                    ->label('Linked login account')->helperText('Optional — links this employee to a user who can log in.'),
            ])->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee_code')->label('Code')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('name')->label('Name')
                    ->state(fn (Employee $record): string => $record->fullName())
                    ->searchable(['first_name', 'last_name']),
                Tables\Columns\TextColumn::make('department.name')->placeholder('—')->sortable(),
                Tables\Columns\TextColumn::make('designation.title')->placeholder('—'),
                Tables\Columns\TextColumn::make('employment_type')->badge()
                    ->formatStateUsing(fn (EmploymentType $state): string => $state->label()),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (EmployeeStatus $state): string => $state->label())
                    ->color(fn (EmployeeStatus $state): string => $state->color()),
                Tables\Columns\TextColumn::make('join_date')->date()->sortable()->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(EmployeeStatus::options()),
                Tables\Filters\SelectFilter::make('department')->relationship('department', 'name'),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->defaultSort('employee_code');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployees::route('/'),
            'create' => Pages\CreateEmployee::route('/create'),
            'edit' => Pages\EditEmployee::route('/{record}/edit'),
        ];
    }
}
