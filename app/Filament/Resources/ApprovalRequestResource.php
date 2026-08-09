<?php

namespace App\Filament\Resources;

use App\Actions\Workflow\ApproveStep;
use App\Actions\Workflow\RejectStep;
use App\Enums\ApprovalStatus;
use App\Exceptions\ApprovalException;
use App\Filament\Resources\ApprovalRequestResource\Pages;
use App\Models\ApprovalRequest;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/**
 * Approvals inbox (spec #25). Read-only list driven by the workflow engine, with
 * Approve/Reject actions that call the domain actions — authorization is enforced
 * there (server-side), the UI only surfaces the buttons to eligible approvers.
 */
class ApprovalRequestResource extends Resource
{
    protected static ?string $model = ApprovalRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-check-badge';

    protected static ?string $navigationGroup = 'Workflow';

    protected static ?string $navigationLabel = 'Approvals';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::query()->where('status', ApprovalStatus::Pending->value)->count();

        return $count > 0 ? (string) $count : null;
    }

    protected static function canActOn(ApprovalRequest $record): bool
    {
        $user = Auth::user();
        $step = $record->currentStep();

        return $user instanceof \App\Models\User
            && $step !== null
            && $record->status === ApprovalStatus::Pending
            && $user->hasRole($step->role);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('approvable_type')->label('Document')
                    ->formatStateUsing(fn (string $state): string => class_basename($state)),
                Tables\Columns\TextColumn::make('amount')->money(config('erp.currency.code'))->sortable(),
                Tables\Columns\TextColumn::make('flow.name')->label('Flow'),
                Tables\Columns\TextColumn::make('current_step')->label('Step'),
                Tables\Columns\TextColumn::make('status')->badge()->colors([
                    'warning' => 'pending', 'success' => 'approved', 'danger' => 'rejected', 'gray' => 'cancelled',
                ]),
                Tables\Columns\TextColumn::make('requester.name')->label('Requested by')->default('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected',
                ])->default('pending'),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->icon('heroicon-o-check')->color('success')
                    ->visible(fn (ApprovalRequest $record): bool => static::canActOn($record))
                    ->form([Forms\Components\Textarea::make('comment')->label('Comment (optional)')])
                    ->action(function (ApprovalRequest $record, array $data): void {
                        try {
                            app(ApproveStep::class)->handle($record, Auth::user(), $data['comment'] ?? null);
                            Notification::make()->title('Approved')->success()->send();
                        } catch (ApprovalException $e) {
                            Notification::make()->title('Cannot approve')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('reject')
                    ->icon('heroicon-o-x-mark')->color('danger')
                    ->visible(fn (ApprovalRequest $record): bool => static::canActOn($record))
                    ->form([Forms\Components\Textarea::make('comment')->label('Reason')])
                    ->action(function (ApprovalRequest $record, array $data): void {
                        try {
                            app(RejectStep::class)->handle($record, Auth::user(), $data['comment'] ?? null);
                            Notification::make()->title('Rejected')->success()->send();
                        } catch (ApprovalException $e) {
                            Notification::make()->title('Cannot reject')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListApprovalRequests::route('/'),
        ];
    }
}
