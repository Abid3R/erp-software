<?php

namespace App\Filament\Pages;

use App\Models\ReportSetting;
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
 * Per-company report/voucher branding (spec #31). An Editor customises how this
 * company's documents print without touching code. Access is Shield-gated
 * (page_ReportSettings), granted to the editor role.
 *
 * @property-read \Filament\Forms\Form $form Filament's form (magic accessor from InteractsWithForms).
 */
class ReportSettings extends Page implements HasForms
{
    use HasPageShield;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-paint-brush';

    protected static ?string $navigationGroup = 'Access';

    protected static ?string $navigationLabel = 'Report Settings';

    protected static ?string $title = 'Report Settings';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.report-settings';

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $this->form->fill($this->setting()->attributesToArray());
    }

    protected function setting(): ReportSetting
    {
        return ReportSetting::firstOrNew(['company_id' => app(CompanyContext::class)->currentId()]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Letterhead')
                    ->description('Shown at the top of every report and voucher for this company.')
                    ->schema([
                        Forms\Components\FileUpload::make('logo_path')->label('Logo')
                            ->image()->disk('public')->directory('report-logos')
                            ->visibility('public')->imageEditor()->maxSize(2048),
                        Forms\Components\Toggle::make('show_logo')->label('Show logo on documents')->default(true),
                        Forms\Components\TextInput::make('header_note')->label('Header line (address / tagline)')->maxLength(255),
                        Forms\Components\TextInput::make('footer_note')->label('Footer line')->maxLength(255),
                    ])->columns(2),
                Forms\Components\Section::make('Vouchers')
                    ->schema([
                        Forms\Components\TextInput::make('signatory_left')->label('Left signature label')->placeholder('Prepared by'),
                        Forms\Components\TextInput::make('signatory_right')->label('Right signature label')->placeholder('Authorised signature'),
                        Forms\Components\Textarea::make('terms')->label('Terms / notes')->rows(3),
                    ])->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $setting = $this->setting();
        $setting->fill($this->form->getState());
        $setting->company_id = app(CompanyContext::class)->currentId();
        $setting->save();

        Notification::make()->title('Report settings saved')->success()->send();
    }

    protected function getFormActions(): array
    {
        return [Action::make('save')->label('Save')->submit('save')];
    }
}
