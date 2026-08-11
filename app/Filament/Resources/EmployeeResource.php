<?php

namespace App\Filament\Resources;

use App\Enums\EmployeeStatus;
use App\Enums\EmploymentType;
use App\Enums\Gender;
use App\Filament\Resources\EmployeeResource\Pages;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Support\CompanyContext;
use App\Support\CsvActions;
use Filament\Forms;
use Illuminate\Support\Carbon;
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
            ->headerActions([
                CsvActions::export(Employee::class, 'employees.csv', self::csvColumns()),
                CsvActions::import([self::class, 'importRow']),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->defaultSort('employee_code');
    }

    /** @return array<string, string|callable> */
    public static function csvColumns(): array
    {
        return [
            'Code' => 'employee_code',
            'FirstName' => 'first_name',
            'LastName' => 'last_name',
            'Email' => 'email',
            'Phone' => 'phone',
            'Department' => fn (Employee $r): string => (string) data_get($r, 'department.name', ''),
            'Designation' => fn (Employee $r): string => (string) data_get($r, 'designation.title', ''),
            'EmploymentType' => fn (Employee $r): string => $r->employment_type->value,
            'Status' => fn (Employee $r): string => $r->status->value,
            'JoinDate' => fn (Employee $r): string => Carbon::parse($r->join_date)->format('Y-m-d'),
            'BaseSalary' => 'base_salary',
        ];
    }

    /** @param array<string, string> $row */
    public static function importRow(array $row): string
    {
        $code = $row['Code'] ?? '';
        $first = $row['FirstName'] ?? '';
        if ($code === '' || $first === '') {
            return 'skipped';
        }

        $existing = Employee::query()->where('employee_code', $code)->first();

        $joinDate = $existing !== null ? Carbon::parse($existing->join_date)->format('Y-m-d') : null;
        if (($row['JoinDate'] ?? '') !== '') {
            try {
                $joinDate = Carbon::parse($row['JoinDate'])->format('Y-m-d');
            } catch (\Throwable) {
                // keep the previous value
            }
        }
        if ($joinDate === null) {
            return 'skipped'; // a new employee needs a join date (NOT NULL)
        }

        $dept = ($row['Department'] ?? '') !== '' ? Department::query()->where('name', $row['Department'])->first() : null;
        $desig = ($row['Designation'] ?? '') !== '' ? Designation::query()->where('title', $row['Designation'])->first() : null;

        $employee = $existing ?? new Employee;
        $employee->fill([
            'employee_code' => $code,
            'first_name' => $first,
            'last_name' => ($row['LastName'] ?? '') ?: null,
            'email' => ($row['Email'] ?? '') ?: null,
            'phone' => ($row['Phone'] ?? '') ?: null,
            'department_id' => $dept?->getKey() ?? $existing?->department_id,
            'designation_id' => $desig?->getKey() ?? $existing?->designation_id,
            'employment_type' => (EmploymentType::tryFrom(self::normalizeEnum($row['EmploymentType'] ?? '')) ?? EmploymentType::Permanent)->value,
            'status' => (EmployeeStatus::tryFrom(self::normalizeEnum($row['Status'] ?? '')) ?? EmployeeStatus::Active)->value,
            'join_date' => $joinDate,
            'base_salary' => ($row['BaseSalary'] ?? '') !== '' ? $row['BaseSalary'] : ($existing->base_salary ?? 0),
        ]);
        $employee->save();

        return $existing !== null ? 'updated' : 'created';
    }

    /** Normalise "Part-time" / "On leave" to enum backing values. */
    private static function normalizeEnum(string $value): string
    {
        return str_replace([' ', '-'], '_', strtolower(trim($value)));
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
