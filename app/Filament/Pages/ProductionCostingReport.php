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
 * Production costing — the full cost breakdown per process order (materials,
 * labour, machine, utilities, overhead) and the actual unit cost. Real data from
 * process_orders.
 */
class ProductionCostingReport extends Page implements HasForms
{
    use HasPageShield;
    use InteractsWithForms;
    use TabularReport;
    use WithReportDateRange;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pages.tabular-report';

    protected static ?string $title = 'Production Costing';

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
        return 'Production Costing';
    }

    public function reportHeaders(): array
    {
        return ['Ref', 'Process', 'Product', 'Produced', 'Material', 'Labour', 'Machine', 'Utility', 'Overhead', 'Total', 'Unit cost'];
    }

    public function reportRows(): array
    {
        $companyId = app(CompanyContext::class)->currentId();
        if ($companyId === null) {
            return [];
        }
        ['from' => $from, 'to' => $to] = $this->resolvedRange();

        return ProcessOrder::query()->where('company_id', $companyId)
            ->where('total_cost', '>', 0)
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->with(['processType', 'outputProduct'])
            ->orderBy('created_at')
            ->get()
            ->map(fn (ProcessOrder $o): array => [
                $o->reference,
                $o->processType?->name ?? '—',
                $o->outputProduct?->name ?? '—',
                $this->qty($o->produced_quantity),
                $this->money($o->material_cost),
                $this->money($o->labour_cost),
                $this->money($o->machine_cost),
                $this->money($o->utility_cost),
                $this->money($o->overhead_cost),
                $this->money($o->total_cost),
                $this->money($o->output_unit_cost),
            ])->all();
    }
}
