<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\TabularReport;
use App\Filament\Concerns\WithReportDateRange;
use App\Models\ProcessOrder;
use App\Support\CompanyContext;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;

/**
 * Rework report — every rework run, the original order it reworks, its quantity and
 * added cost. Real data from process_orders flagged as rework.
 */
class ReworkReport extends Page implements HasForms
{
    use HasPageShield;
    use InteractsWithForms;
    use TabularReport;
    use WithReportDateRange;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pages.tabular-report';

    protected static ?string $title = 'Rework';

    public function form(Form $form): Form
    {
        return $form->schema($this->rangeSchema())->columns(3)->statePath('');
    }

    protected function getHeaderActions(): array
    {
        return $this->tabularActions();
    }

    public function reportTitle(): string
    {
        return 'Rework';
    }

    public function reportHeaders(): array
    {
        return ['Date', 'Rework ref', 'Original', 'Process', 'Product', 'Qty', 'Status', 'Rework cost'];
    }

    public function reportRows(): array
    {
        $companyId = app(CompanyContext::class)->currentId();
        if ($companyId === null) {
            return [];
        }
        ['from' => $from, 'to' => $to] = $this->resolvedRange();

        return ProcessOrder::query()->where('company_id', $companyId)->where('is_rework', true)
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->with(['reworkOf', 'processType', 'outputProduct'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (ProcessOrder $o): array => [
                $o->created_at->toDateString(),
                $o->reference,
                $o->reworkOf?->reference ?? '—',
                $o->processType?->name ?? '—',
                $o->outputProduct?->name ?? '—',
                $this->qty($o->planned_quantity),
                $o->status->label(),
                $this->money($o->total_cost),
            ])
            ->all();
    }
}
