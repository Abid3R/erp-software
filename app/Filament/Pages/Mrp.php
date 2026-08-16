<?php

namespace App\Filament\Pages;

use App\Domain\Manufacturing\MrpPlanner;
use App\Support\CompanyContext;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;

/**
 * Material Requirements Planning view. Shows what to purchase or manufacture to
 * cover open sales-order demand (computation lives in MrpPlanner).
 */
class Mrp extends Page
{
    use HasPageShield;

    protected static ?string $navigationIcon = 'heroicon-o-square-3-stack-3d';

    protected static ?string $navigationGroup = 'Manufacturing';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'MRP';

    protected static ?string $navigationLabel = 'MRP';

    protected static string $view = 'filament.pages.mrp';

    /** @return list<array{product_id: int, product: string, demand: string, on_hand: string, incoming: string, net: string, action: string}> */
    public function getSuggestions(): array
    {
        $companyId = app(CompanyContext::class)->currentId();

        return $companyId === null ? [] : MrpPlanner::plan($companyId);
    }

    /** Trim a decimal string for display (drop trailing zeros). */
    public function qty(string $value): string
    {
        return rtrim(rtrim($value, '0'), '.') ?: '0';
    }
}
