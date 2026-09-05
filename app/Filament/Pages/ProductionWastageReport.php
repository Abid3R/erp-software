<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\TabularReport;
use App\Filament\Concerns\WithReportDateRange;
use App\Models\ProcessOrder;
use App\Support\CompanyContext;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Brick\Math\BigDecimal;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;

/**
 * Production wastage — process wastage plus QC rejects per order in a period. Real
 * data from process_orders and their quality inspections.
 */
class ProductionWastageReport extends Page implements HasForms
{
    use HasPageShield;
    use InteractsWithForms;
    use TabularReport;
    use WithReportDateRange;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pages.tabular-report';

    protected static ?string $title = 'Production Wastage';

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
        return 'Production Wastage';
    }

    public function reportHeaders(): array
    {
        return ['Date', 'Ref', 'Process', 'Product', 'Produced', 'Wastage', 'QC rejected'];
    }

    public function reportRows(): array
    {
        $companyId = app(CompanyContext::class)->currentId();
        if ($companyId === null) {
            return [];
        }
        ['from' => $from, 'to' => $to] = $this->resolvedRange();

        return ProcessOrder::query()->where('company_id', $companyId)
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->with(['processType', 'outputProduct', 'qualityInspections'])
            ->orderBy('created_at')
            ->get()
            ->map(function (ProcessOrder $o): ?array {
                $rejected = $o->qualityInspections->reduce(fn (BigDecimal $c, $i) => $c->plus($i->rejected_quantity), BigDecimal::zero());
                $wastage = BigDecimal::of($o->wastage_quantity);
                if ($wastage->plus($rejected)->isZero()) {
                    return null; // only orders with loss
                }

                return [
                    $o->created_at->toDateString(),
                    $o->reference,
                    $o->processType?->name ?? '—',
                    $o->outputProduct?->name ?? '—',
                    $this->qty($o->produced_quantity),
                    $this->qty((string) $wastage),
                    $this->qty((string) $rejected),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
