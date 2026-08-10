<?php

namespace App\Filament\Resources;

use App\Actions\Hr\ApproveLeave;
use App\Actions\Hr\RejectLeave;
use App\Domain\Hr\LeaveBalance;
use App\Enums\ApprovalStatus;
use App\Exceptions\ApprovalException;
use App\Filament\Resources\LeaveRequestResource\Pages;
use App\Models\Employee;
use App\Models\LeaveRequest;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class LeaveRequestResource extends Resource
{
    protected static ?string $model = LeaveRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar';

    protected static ?string $navigationGroup = 'HR';

    protected static ?int $navigationSort = 8;

    protected static ?string $navigationLabel = 'Leave Requests';

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::query()->where('status', ApprovalStatus::Pending->value)->count();

        return $count > 0 ? (string) $count : null;
    }

    protected static function canDecide(LeaveRequest $record): bool
    {
        $user = Auth::user();

        return $record->status === ApprovalStatus::Pending
            && $user instanceof \App\Models\User
            && $user->hasAnyRole(['super_admin', 'hr', 'manager']);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['employee', 'leaveType']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('employee_id')->relationship('employee', 'first_name')
                ->getOptionLabelFromRecordUsing(fn (Employee $r): string => $r->employee_code.' — '.$r->fullName())
                ->searchable()->preload()->required(),
            Forms\Components\Select::make('leave_type_id')->relationship('leaveType', 'name')->required(),
            Forms\Components\DatePicker::make('start_date')->required()->default(now()),
            Forms\Components\DatePicker::make('end_date')->required()->default(now())
                ->afterOrEqual('start_date'),
            Forms\Components\Textarea::make('reason')->maxLength(255)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee.first_name')->label('Employee')
                    ->state(fn (LeaveRequest $r): string => $r->employee?->fullName() ?? '—')->searchable(),
                Tables\Columns\TextColumn::make('leaveType.name')->label('Type'),
                Tables\Columns\TextColumn::make('start_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('end_date')->date(),
                Tables\Columns\TextColumn::make('days'),
                Tables\Columns\TextColumn::make('balance')->label('Remaining')
                    ->state(fn (LeaveRequest $r): string => $r->employee && $r->leaveType
                        ? LeaveBalance::remaining($r->employee, $r->leaveType, (int) $r->start_date->format('Y')).' d'
                        : '—'),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (ApprovalStatus $s): string => $s->label())
                    ->color(fn (ApprovalStatus $s): string => match ($s) {
                        ApprovalStatus::Approved => 'success',
                        ApprovalStatus::Rejected => 'danger',
                        ApprovalStatus::Cancelled => 'gray',
                        ApprovalStatus::Pending => 'warning',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(ApprovalStatus::class)->default('pending'),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')->icon('heroicon-o-check')->color('success')
                    ->visible(fn (LeaveRequest $record): bool => static::canDecide($record))
                    ->form([Forms\Components\Textarea::make('note')->label('Note (optional)')])
                    ->action(function (LeaveRequest $record, array $data): void {
                        try {
                            app(ApproveLeave::class)->handle($record, Auth::user(), $data['note'] ?? null);
                            Notification::make()->title('Leave approved')->success()->send();
                        } catch (ApprovalException $e) {
                            Notification::make()->title('Cannot approve')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('reject')->icon('heroicon-o-x-mark')->color('danger')
                    ->visible(fn (LeaveRequest $record): bool => static::canDecide($record))
                    ->form([Forms\Components\Textarea::make('note')->label('Reason')])
                    ->action(function (LeaveRequest $record, array $data): void {
                        try {
                            app(RejectLeave::class)->handle($record, Auth::user(), $data['note'] ?? null);
                            Notification::make()->title('Leave rejected')->success()->send();
                        } catch (ApprovalException $e) {
                            Notification::make()->title('Cannot reject')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\EditAction::make()
                    ->visible(fn (LeaveRequest $record): bool => $record->status === ApprovalStatus::Pending),
            ])
            ->defaultSort('start_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLeaveRequests::route('/'),
            'create' => Pages\CreateLeaveRequest::route('/create'),
            'edit' => Pages\EditLeaveRequest::route('/{record}/edit'),
        ];
    }
}
