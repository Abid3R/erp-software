<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\TabularReport;
use App\Filament\Concerns\WithReportDateRange;
use App\Models\FabricRoll;
use App\Support\CompanyContext;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;

/**
 * Roll traceability — every fabric roll with its batch, the process order that made
 * it, and the input batches consumed to produce it (backward trace). Real data.
 */
class RollTraceabilityReport extends Page implements HasForms
{
    use HasPageShield;
    use InteractsWithForms;
    use TabularReport;
    use WithReportDateRange;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pages.tabular-report';

    protected static ?string $title = 'Roll Traceability';

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
        return 'Roll Traceability';
    }

    public function reportHeaders(): array
    {
        return ['Roll', 'Product', 'Batch', 'From order', 'Weight', 'GSM', 'QC', 'Source input batches'];
    }

    public function reportRows(): array
    {
        $companyId = app(CompanyContext::class)->currentId();
        if ($companyId === null) {
            return [];
        }
        ['from' => $from, 'to' => $to] = $this->resolvedRange();

        return FabricRoll::query()->where('company_id', $companyId)
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->with(['product', 'processOrder', 'batch.consumedBatches.product'])
            ->orderByDesc('id')
            ->get()
            ->map(function (FabricRoll $roll): array {
                $sources = $roll->batch?->consumedBatches
                    ->map(fn ($b): string => $b->batch_number.' ('.($b->product?->name ?? '—').')')
                    ->join(', ') ?: '—';

                return [
                    $roll->roll_number,
                    $roll->product?->name ?? '—',
                    $roll->batch?->batch_number ?? '—',
                    $roll->processOrder?->reference ?? '—',
                    $this->qty((string) $roll->weight),
                    $roll->gsm ?: '—',
                    ucfirst($roll->qc_status),
                    $sources,
                ];
            })
            ->all();
    }
}
