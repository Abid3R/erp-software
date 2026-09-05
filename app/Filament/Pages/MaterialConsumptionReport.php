<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\TabularReport;
use App\Filament\Concerns\WithReportDateRange;
use App\Models\ProcessOrderInput;
use App\Support\CompanyContext;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Brick\Math\BigDecimal;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;

/**
 * Material consumption — inputs consumed by process orders in a period, aggregated
 * per material. Real data from process_order_inputs.
 */
class MaterialConsumptionReport extends Page implements HasForms
{
    use HasPageShield;
    use InteractsWithForms;
    use TabularReport;
    use WithReportDateRange;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pages.tabular-report';

    protected static ?string $title = 'Material Consumption';

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
        return 'Material Consumption';
    }

    public function reportHeaders(): array
    {
        return ['SKU', 'Material', 'Consumed qty', 'Total cost'];
    }

    public function reportRows(): array
    {
        $companyId = app(CompanyContext::class)->currentId();
        if ($companyId === null) {
            return [];
        }
        ['from' => $from, 'to' => $to] = $this->resolvedRange();

        return ProcessOrderInput::query()->where('company_id', $companyId)
            ->where('consumed_quantity', '>', 0)
            ->whereHas('processOrder', fn ($q) => $q
                ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
                ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to)))
            ->with('product')
            ->get()
            ->groupBy('product_id')
            ->map(function ($group) {
                $qty = $group->reduce(fn (BigDecimal $c, $i) => $c->plus($i->consumed_quantity), BigDecimal::zero());
                $cost = $group->reduce(fn (BigDecimal $c, $i) => $c->plus($i->total_cost), BigDecimal::zero());
                $product = $group->first()->product;

                return [
                    $product?->sku ?? '—',
                    $product?->name ?? '—',
                    $this->qty((string) $qty),
                    $this->money((string) $cost),
                ];
            })
            ->sortBy(1)
            ->values()
            ->all();
    }
}
