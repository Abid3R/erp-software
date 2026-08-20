<?php

namespace App\Filament\Widgets\Dashboard;

use App\Filament\Resources\StockResource;
use App\Models\Product;
use App\Models\StockBalance;
use App\Support\CompanyContext;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Query\Expression;

/**
 * Products at or below their configured reorder_level. Reuses the same
 * definition as the Operational KPI card and the Stocks resource — no
 * new business rule introduced.
 */
class LowStockTable extends BaseWidget
{
    use HasWidgetShield;

    protected static ?string $heading = 'Low Stock — at or below reorder level';

    protected static ?int $sort = 20;

    protected int|string|array $columnSpan = ['default' => 'full', 'md' => 6];

    public function table(Table $table): Table
    {
        return $table
            ->query($this->baseQuery())
            ->emptyStateHeading('Stock is healthy')
            ->emptyStateDescription('Nothing is at or below its reorder level.')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->columns([
                Tables\Columns\TextColumn::make('sku')->label('SKU')->searchable(),
                Tables\Columns\TextColumn::make('name')->label('Product')->searchable()->wrap(),
                Tables\Columns\TextColumn::make('on_hand')->label('On hand')->numeric(decimalPlaces: 2)->sortable(),
                Tables\Columns\TextColumn::make('reorder_level')->label('Reorder')->numeric(decimalPlaces: 2)->sortable(),
                Tables\Columns\BadgeColumn::make('shortfall')
                    ->label('Short by')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2))
                    ->color(fn ($state): string => ((float) $state) > 0 ? 'danger' : 'warning'),
            ])
            ->recordUrl(fn () => StockResource::getUrl())
            ->defaultSort('shortfall', 'desc');
    }

    /**
     * @return Builder<Product>
     */
    private function baseQuery(): Builder
    {
        $companyId = app(CompanyContext::class)->currentId();

        $onHand = StockBalance::query()
            ->select('product_id')
            ->selectRaw('COALESCE(SUM(quantity_on_hand), 0) as qty')
            ->when($companyId !== null, fn ($q) => $q->where('company_id', $companyId))
            ->groupBy('product_id');

        return Product::query()
            ->when($companyId !== null, fn ($q) => $q->where('products.company_id', $companyId))
            ->whereNotNull('reorder_level')
            ->where('reorder_level', '>', 0)
            ->leftJoinSub($onHand, 'oh', 'oh.product_id', '=', 'products.id')
            ->select([
                'products.id',
                'products.sku',
                'products.name',
                'products.reorder_level',
                new Expression('COALESCE(oh.qty, 0) as on_hand'),
                new Expression('(products.reorder_level - COALESCE(oh.qty, 0)) as shortfall'),
            ])
            ->whereRaw('COALESCE(oh.qty, 0) <= products.reorder_level');
    }
}
