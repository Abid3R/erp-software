<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\TabularReport;
use App\Filament\Concerns\WithReportDateRange;
use App\Models\Machine;
use App\Models\ProcessOrder;
use App\Support\CompanyContext;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;

/**
 * Machine / production performance — per machine, the number of runs, quantity
 * produced, wastage and cost over a period. Real data from process_orders.
 */
class MachinePerformanceReport extends Page implements HasForms
{
    use HasPageShield;
    use InteractsWithForms;
    use TabularReport;
    use WithReportDateRange;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pages.tabular-report';

    protected static ?string $title = 'Machine Performance';

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
        return 'Machine Performance';
    }

    public function reportHeaders(): array
    {
        return ['Machine', 'Type', 'Runs', 'Produced', 'Wastage', 'Total cost'];
    }

    public function reportRows(): array
    {
        $companyId = app(CompanyContext::class)->currentId();
        if ($companyId === null) {
            return [];
        }
        ['from' => $from, 'to' => $to] = $this->resolvedRange();

        $agg = ProcessOrder::query()->where('company_id', $companyId)
            ->whereNotNull('machine_id')
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->toBase()
            ->selectRaw('machine_id, COUNT(*) as runs, COALESCE(SUM(produced_quantity),0) as produced, COALESCE(SUM(wastage_quantity),0) as wastage, COALESCE(SUM(total_cost),0) as cost')
            ->groupBy('machine_id')
            ->get()
            ->keyBy('machine_id');

        if ($agg->isEmpty()) {
            return [];
        }

        $machines = Machine::query()->whereIn('id', $agg->keys())->get()->keyBy('id');

        return $agg->map(function ($row) use ($machines): array {
            $machine = $machines->get($row->machine_id);

            return [
                $machine?->name ?? ('#'.$row->machine_id),
                $machine?->type ?? '—',
                (string) $row->runs,
                $this->qty((string) $row->produced),
                $this->qty((string) $row->wastage),
                $this->money((string) $row->cost),
            ];
        })->sortBy(0)->values()->all();
    }
}
