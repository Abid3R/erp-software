<?php

namespace App\Filament\Pages;

use App\Filament\Resources\PurchaseOrderResource;
use App\Filament\Resources\QuotationResource;
use App\Filament\Resources\SalesOrderResource;
use App\Filament\Resources\StockAdjustmentResource;
use App\Support\DashboardFilter;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Dashboard as BaseDashboard;

/**
 * Custom dashboard page: adds a date-range filter, refresh, and quick-action
 * buttons for the most common create flows. Widgets pick the range up from
 * DashboardFilter — nothing about the underlying computations changes.
 */
class Dashboard extends BaseDashboard implements HasForms
{
    use InteractsWithForms;

    public ?string $from = null;

    public ?string $to = null;

    public function mount(): void
    {
        ['from' => $this->from, 'to' => $this->to] = DashboardFilter::range();
    }

    /**
     * Register `filtersForm` alongside the default `form` so the base dashboard
     * blade can render it. Without this, dynamic property resolution falls
     * through to the infolist trait and calls `filtersForm(Infolist)`.
     *
     * @return array<int|string, string>
     */
    protected function getForms(): array
    {
        return ['filtersForm'];
    }

    public function filtersForm(Form $form): Form
    {
        return $form
            ->schema([
                DatePicker::make('from')->label('From')->native(false)->live(debounce: 300)
                    ->afterStateUpdated(fn () => $this->persistFilter()),
                DatePicker::make('to')->label('To')->native(false)->live(debounce: 300)
                    ->afterStateUpdated(fn () => $this->persistFilter()),
            ])
            ->columns(['default' => 1, 'md' => 2])
            ->statePath('');
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('newQuotation')->label('New Quotation')
                    ->icon('heroicon-o-document-text')
                    ->url(fn () => QuotationResource::getUrl('create')),
                Action::make('newSalesOrder')->label('New Sales Order')
                    ->icon('heroicon-o-shopping-cart')
                    ->url(fn () => SalesOrderResource::getUrl('create')),
                Action::make('newPurchaseOrder')->label('New Purchase Order')
                    ->icon('heroicon-o-truck')
                    ->url(fn () => PurchaseOrderResource::getUrl('create')),
                Action::make('newStockAdjustment')->label('New Stock Adjustment')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->url(fn () => StockAdjustmentResource::getUrl('create')),
            ])
                ->label('Quick actions')
                ->icon('heroicon-o-bolt')
                ->button(),

            Action::make('resetRange')
                ->label('Last 12 months')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->action(function (): void {
                    DashboardFilter::reset();
                    ['from' => $this->from, 'to' => $this->to] = DashboardFilter::range();
                    $this->dispatch('$refresh');
                }),

            Action::make('refresh')
                ->label('Refresh')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->action(fn () => $this->dispatch('$refresh')),
        ];
    }

    /**
     * @return int | string | array<string, int | string | null>
     */
    public function getColumns(): int | string | array
    {
        return ['default' => 1, 'md' => 2, 'xl' => 12];
    }

    private function persistFilter(): void
    {
        DashboardFilter::set($this->from, $this->to);
        $this->dispatch('$refresh');
    }
}
