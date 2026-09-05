<?php

namespace App\Filament\Pages;

use App\Actions\Notifications\SendLowStockAlerts;
use App\Actions\Notifications\SendOverdueInvoiceAlerts;
use App\Models\NotificationSetting;
use App\Support\CompanyContext;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Per-company notification preferences: which operational alerts the system
 * raises (leave approvals, overdue receivables, low stock) and the overdue-days
 * threshold. Access is Shield-gated (page_NotificationSettings).
 *
 * @property-read \Filament\Forms\Form $form
 */
class NotificationSettings extends Page implements HasForms
{
    use HasPageShield;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Notification Settings';

    protected static ?string $title = 'Notification Settings';

    protected static ?int $navigationSort = 5;

    protected static string $view = 'filament.pages.notification-settings';

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $this->form->fill($this->setting()->attributesToArray());
    }

    protected function setting(): NotificationSetting
    {
        return NotificationSetting::firstOrNew(['company_id' => app(CompanyContext::class)->currentId()]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Operational alerts')
                    ->description('Choose which in-app notifications this company receives. Alerts go to the relevant roles automatically.')
                    ->icon('heroicon-o-bell-alert')
                    ->schema([
                        Forms\Components\Toggle::make('leave_approvals_enabled')
                            ->label('Leave approvals')
                            ->helperText('Notify HR and managers the moment an employee submits a leave request.')
                            ->inline(false)
                            ->default(true),

                        Forms\Components\Toggle::make('overdue_invoices_enabled')
                            ->label('Overdue invoices')
                            ->helperText('Alert accounting and managers when customers owe money past the threshold below.')
                            ->inline(false)
                            ->live()
                            ->default(true),

                        Forms\Components\TextInput::make('overdue_days')
                            ->label('Overdue after (days)')
                            ->numeric()->minValue(1)->maxValue(365)->default(30)
                            ->helperText('Receivables older than this are treated as overdue.')
                            ->visible(fn (Forms\Get $get): bool => (bool) $get('overdue_invoices_enabled')),

                        Forms\Components\Toggle::make('low_stock_enabled')
                            ->label('Low stock')
                            ->helperText('Alert inventory and purchasing when products reach or fall below their reorder level.')
                            ->inline(false)
                            ->default(true),

                        Forms\Components\Toggle::make('production_alerts_enabled')
                            ->label('Production alerts')
                            ->helperText('Alert production and management when process orders are waiting at the QC gate.')
                            ->inline(false)
                            ->default(true),
                    ])->columns(1),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $setting = $this->setting();
        $setting->fill($this->form->getState());
        $setting->company_id = app(CompanyContext::class)->currentId();
        $setting->save();

        Notification::make()->title('Notification settings saved')->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendNow')
                ->label('Send alerts now')
                ->icon('heroicon-o-paper-airplane')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Immediately evaluate low-stock and overdue-receivable alerts for this company and notify the relevant roles.')
                ->action(function (): void {
                    $companyId = app(CompanyContext::class)->currentId();
                    if ($companyId === null) {
                        return;
                    }

                    $low = app(SendLowStockAlerts::class)->forCompany($companyId);
                    $overdue = app(SendOverdueInvoiceAlerts::class)->forCompany($companyId);
                    $production = app(\App\Actions\Notifications\SendProductionAlerts::class)->forCompany($companyId);

                    Notification::make()
                        ->title('Alerts evaluated')
                        ->body("Low-stock products: {$low}. Overdue customers: {$overdue}. Awaiting QC: {$production}.")
                        ->success()
                        ->send();
                }),
        ];
    }

    protected function getFormActions(): array
    {
        return [Action::make('save')->label('Save')->submit('save')];
    }
}
