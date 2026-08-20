<?php

namespace App\Filament\Pages;

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
 * Per-company delivery policy (spec: DO module — configurable workflow). Controls
 * whether sales orders must go through a Delivery Order document, and whether
 * negative stock is allowed. Writes directly to the company record.
 *
 * @property-read \Filament\Forms\Form $form
 */
class DeliverySettings extends Page implements HasForms
{
    use HasPageShield;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-8-tooth';

    protected static ?string $navigationGroup = 'Sales';

    protected static ?string $navigationLabel = 'Delivery Settings';

    protected static ?string $title = 'Delivery Settings';

    protected static ?int $navigationSort = 9;

    protected static string $view = 'filament.pages.delivery-settings';

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $company = app(CompanyContext::class)->current();
        $this->form->fill([
            'require_delivery_order' => (bool) ($company?->require_delivery_order ?? false),
            'allow_negative_stock' => (bool) ($company?->allow_negative_stock ?? false),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Delivery workflow')
                    ->description('Choose how goods leave the warehouse for a sales order.')
                    ->schema([
                        Forms\Components\Toggle::make('require_delivery_order')
                            ->label('Require a Delivery Order before invoicing')
                            ->helperText('On: Sales Order → Delivery Order (challan) → Invoice. Off: the existing direct Deliver action on the sales order is used.'),
                        Forms\Components\Toggle::make('allow_negative_stock')
                            ->label('Allow negative stock')
                            ->helperText('Off (recommended): a delivery is blocked when on-hand stock is insufficient.'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $company = app(CompanyContext::class)->current();
        abort_if($company === null, 400, 'No active company.');

        $state = $this->form->getState();
        $company->update([
            'require_delivery_order' => (bool) ($state['require_delivery_order'] ?? false),
            'allow_negative_stock' => (bool) ($state['allow_negative_stock'] ?? false),
        ]);

        Notification::make()->title('Delivery settings saved')->success()->send();
    }

    protected function getFormActions(): array
    {
        return [Action::make('save')->label('Save')->submit('save')];
    }
}
