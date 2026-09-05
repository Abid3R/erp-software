<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\TabularReport;
use App\Filament\Concerns\WithReportDateRange;
use App\Models\ProcessOrder;
use App\Models\ProcessType;
use App\Support\CompanyContext;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;

/**
 * Production report — process runs (knitting, dyeing, finishing) in a period, with
 * planned/produced/wastage and cost. Filter by process to get a knitting-only or
 * dyeing-only view. Real data from process_orders.
 */
class ProductionReport extends Page implements HasForms
{
    use HasPageShield;
    use InteractsWithForms;
    use TabularReport;
    use WithReportDateRange;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pages.tabular-report';

    protected static ?string $title = 'Production Report';

    public ?int $processType = null;

    public function form(Form $form): Form
    {
        return $form->schema([
            ...$this->rangeSchema(),
            Select::make('processType')->label('Process')
                ->options(fn (): array => ProcessType::query()->orderBy('sort')->pluck('name', 'id')->all())
                ->placeholder('All processes')->live(),
        ])->columns(4)->statePath('');
    }

    protected function getHeaderActions(): array
    {
        return $this->tabularActions();
    }

    public function reportTitle(): string
    {
        return 'Production Report';
    }

    public function reportHeaders(): array
    {
        return ['Date', 'Ref', 'Process', 'Output', 'Planned', 'Produced', 'Wastage', 'Unit cost', 'Total cost'];
    }

    public function reportRows(): array
    {
        $companyId = app(CompanyContext::class)->currentId();
        if ($companyId === null) {
            return [];
        }
        ['from' => $from, 'to' => $to] = $this->resolvedRange();

        return ProcessOrder::query()->where('company_id', $companyId)
            ->where('produced_quantity', '>', 0)
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->when($this->processType, fn ($q) => $q->where('process_type_id', $this->processType))
            ->with(['processType', 'outputProduct'])
            ->orderBy('created_at')
            ->get()
            ->map(fn (ProcessOrder $o): array => [
                $o->created_at->toDateString(),
                $o->reference,
                $o->processType?->name ?? '—',
                $o->outputProduct?->name ?? '—',
                $this->qty($o->planned_quantity),
                $this->qty($o->produced_quantity),
                $this->qty($o->wastage_quantity),
                $this->money($o->output_unit_cost),
                $this->money($o->total_cost),
            ])->all();
    }
}
