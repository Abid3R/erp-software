<?php

namespace App\Filament\Resources;

use App\Enums\PayrollStatus;
use App\Filament\Resources\PayrollRunResource\Pages;
use App\Filament\Resources\PayrollRunResource\RelationManagers\PayslipsRelationManager;
use App\Models\Employee;
use App\Models\PayrollRun;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

class PayrollRunResource extends Resource
{
    protected static ?string $model = PayrollRun::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-bangladeshi';

    protected static ?string $navigationGroup = 'HR';

    protected static ?int $navigationSort = 9;

    protected static ?string $navigationLabel = 'Payroll';

    /** @return array<int, string> */
    private static function monthOptions(): array
    {
        return collect(range(1, 12))->mapWithKeys(fn (int $m): array => [$m => Carbon::create(2000, $m, 1)->format('F')])->all();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required()->default('Payroll '.now()->format('M Y')),
            Forms\Components\Select::make('month')->options(self::monthOptions())->default((int) now()->month)->required(),
            Forms\Components\TextInput::make('year')->numeric()->default((int) now()->year)->required(),
            Forms\Components\Select::make('employees')->multiple()->required()
                ->options(fn (): array => Employee::query()->where('status', 'active')->get()
                    ->mapWithKeys(fn (Employee $e) => [$e->getKey() => $e->employee_code.' — '.$e->fullName()])->all())
                ->helperText('A payslip is created for each active employee at their base salary.'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('period')->state(fn (PayrollRun $r): string => $r->periodLabel()),
                Tables\Columns\TextColumn::make('payslips_count')->counts('payslips')->label('Payslips'),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (PayrollStatus $s): string => $s->label())
                    ->color(fn (PayrollStatus $s): string => $s->color()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('finalize')->icon('heroicon-o-lock-closed')->color('success')
                    ->visible(fn (PayrollRun $record): bool => $record->status === PayrollStatus::Draft)
                    ->requiresConfirmation()
                    ->action(function (PayrollRun $record): void {
                        $record->update(['status' => PayrollStatus::Finalized]);
                        Notification::make()->title('Payroll finalized')->success()->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [PayslipsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayrollRuns::route('/'),
            'create' => Pages\CreatePayrollRun::route('/create'),
            'view' => Pages\ViewPayrollRun::route('/{record}'),
        ];
    }
}
