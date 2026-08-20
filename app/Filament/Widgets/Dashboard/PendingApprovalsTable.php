<?php

namespace App\Filament\Widgets\Dashboard;

use App\Enums\ApprovalStatus;
use App\Filament\Resources\ApprovalRequestResource;
use App\Models\ApprovalRequest;
use App\Support\CompanyContext;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * Pending items from the workflow inbox. Read-only — actions live inside the
 * Approvals resource (permissions there are unchanged).
 */
class PendingApprovalsTable extends BaseWidget
{
    use HasWidgetShield;

    protected static ?string $heading = 'Pending Approvals';

    protected static ?int $sort = 21;

    protected int|string|array $columnSpan = ['default' => 'full', 'md' => 6];

    public function table(Table $table): Table
    {
        return $table
            ->query($this->baseQuery())
            ->emptyStateHeading('Inbox is empty')
            ->emptyStateDescription('Nothing is waiting on an approval right now.')
            ->emptyStateIcon('heroicon-o-inbox')
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Requested')->since()->sortable(),
                Tables\Columns\TextColumn::make('flow.name')->label('Flow'),
                Tables\Columns\TextColumn::make('approvable_type')
                    ->label('Subject')
                    ->formatStateUsing(fn ($state): string => class_basename((string) $state)),
                Tables\Columns\TextColumn::make('amount')->money(config('erp.currency.code'))->sortable(),
                Tables\Columns\TextColumn::make('requester.name')->label('Requested by')->placeholder('—'),
                Tables\Columns\BadgeColumn::make('status')->color('warning')
                    ->formatStateUsing(fn ($state) => is_object($state) ? $state->value : $state),
            ])
            ->recordUrl(fn () => ApprovalRequestResource::getUrl())
            ->defaultSort('created_at', 'desc');
    }

    /**
     * @return Builder<ApprovalRequest>
     */
    private function baseQuery(): Builder
    {
        $companyId = app(CompanyContext::class)->currentId();

        return ApprovalRequest::query()
            ->when($companyId !== null, fn ($q) => $q->where('company_id', $companyId))
            ->where('status', ApprovalStatus::Pending->value)
            ->with(['flow:id,name', 'requester:id,name']);
    }
}
