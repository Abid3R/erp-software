<?php

namespace App\Filament\Pages;

use App\Domain\Crm\PipelineSummary;
use App\Support\CompanyContext;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;

/**
 * Sales pipeline overview (spec: CRM). Open opportunities by stage with total and
 * probability-weighted value (computation in PipelineSummary).
 */
class Pipeline extends Page
{
    use HasPageShield;

    protected static ?string $navigationIcon = 'heroicon-o-funnel';

    protected static ?string $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Pipeline';

    protected static string $view = 'filament.pages.pipeline';

    /** @return list<array{stage: string, label: string, count: int, total: string, weighted: string}> */
    public function getStages(): array
    {
        $companyId = app(CompanyContext::class)->currentId();

        return $companyId === null ? [] : PipelineSummary::for($companyId);
    }

    public function money(string $value): string
    {
        return config('erp.currency.symbol').number_format((float) $value, (int) config('erp.currency.precision', 2));
    }
}
