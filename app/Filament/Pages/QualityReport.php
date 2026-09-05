<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\TabularReport;
use App\Filament\Concerns\WithReportDateRange;
use App\Models\QualityInspection;
use App\Support\CompanyContext;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;

/**
 * QC report — quality inspections in a period with pass/reject outcomes. Real data
 * from quality_inspections.
 */
class QualityReport extends Page implements HasForms
{
    use HasPageShield;
    use InteractsWithForms;
    use TabularReport;
    use WithReportDateRange;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pages.tabular-report';

    protected static ?string $title = 'QC Report';

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
        return 'QC Report';
    }

    public function reportHeaders(): array
    {
        return ['Date', 'Ref', 'Order', 'Product', 'Inspected', 'Passed', 'Rejected', 'Status'];
    }

    public function reportRows(): array
    {
        $companyId = app(CompanyContext::class)->currentId();
        if ($companyId === null) {
            return [];
        }
        ['from' => $from, 'to' => $to] = $this->resolvedRange();

        return QualityInspection::query()->where('company_id', $companyId)
            ->when($from, fn ($q) => $q->whereDate('inspected_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('inspected_at', '<=', $to))
            ->with(['inspectable', 'product'])
            ->orderBy('inspected_at')
            ->get()
            ->map(fn (QualityInspection $i): array => [
                $i->inspected_at?->toDateString() ?? '—',
                $i->reference,
                $i->inspectable?->reference ?? '—',
                $i->product?->name ?? '—',
                $this->qty($i->inspected_quantity),
                $this->qty($i->passed_quantity),
                $this->qty($i->rejected_quantity),
                $i->status->label(),
            ])->all();
    }
}
