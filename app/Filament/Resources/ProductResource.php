<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Support\CompanyContext;
use App\Support\CsvActions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Manufacturing';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Product')->columns(2)->schema([
                Forms\Components\TextInput::make('name')->required()->maxLength(255),
                Forms\Components\TextInput::make('sku')
                    ->label('SKU')
                    ->required()
                    ->maxLength(255)
                    // Uniqueness is per-company; company_id is stamped server-side.
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where(
                        'company_id',
                        app(CompanyContext::class)->currentId(),
                    )),
                Forms\Components\TextInput::make('barcode')->maxLength(255),
                Forms\Components\Select::make('unit_id')
                    ->label('Stock unit')
                    ->relationship('unit', 'name')
                    ->required()
                    ->preload(),
                Forms\Components\Select::make('category_id')
                    ->relationship('category', 'name')
                    ->searchable()->preload(),
                Forms\Components\Select::make('brand_id')
                    ->relationship('brand', 'name')
                    ->searchable()->preload(),
            ]),
            Forms\Components\Section::make('Pricing & stock')->columns(2)->schema([
                Forms\Components\TextInput::make('cost_price')
                    ->numeric()->default(0)->minValue(0)
                    ->prefix(config('erp.currency.symbol')),
                Forms\Components\TextInput::make('selling_price')
                    ->numeric()->default(0)->minValue(0)
                    ->prefix(config('erp.currency.symbol')),
                Forms\Components\TextInput::make('reorder_level')->numeric()->minValue(0),
                Forms\Components\Toggle::make('is_active')->default(true),
                Forms\Components\Toggle::make('tracks_batch')->label('Track batches'),
                Forms\Components\Toggle::make('tracks_serial')->label('Track serials'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sku')->label('SKU')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('category.name')->label('Category')->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('unit.code')->label('Unit'),
                Tables\Columns\TextColumn::make('selling_price')
                    ->money(config('erp.currency.code'))->sortable(),
                Tables\Columns\IconColumn::make('is_active')->boolean()->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Active'),
            ])
            ->headerActions([
                CsvActions::export(Product::class, 'products.csv', self::csvColumns()),
                CsvActions::import([self::class, 'importRow']),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    /**
     * Export headers match the create form's field labels — so a user can open the
     * exported CSV in Excel, add rows, and re-import without renaming any columns.
     *
     * @return array<string, string|callable>
     */
    public static function csvColumns(): array
    {
        return [
            'Name' => 'name',
            'SKU' => 'sku',
            'Barcode' => 'barcode',
            'Stock unit' => fn (Product $r): string => (string) ($r->unit->code ?? $r->unit->name ?? ''),
            'Category' => fn (Product $r): string => (string) ($r->category->name ?? ''),
            'Brand' => fn (Product $r): string => (string) ($r->brand->name ?? ''),
            'Cost price' => 'cost_price',
            'Selling price' => 'selling_price',
            'Reorder level' => 'reorder_level',
            'Is active' => fn (Product $r): string => $r->is_active ? 'yes' : 'no',
            'Track batches' => fn (Product $r): string => $r->tracks_batch ? 'yes' : 'no',
            'Track serials' => fn (Product $r): string => $r->tracks_serial ? 'yes' : 'no',
        ];
    }

    /** @param array<string, string> $row */
    public static function importRow(array $row): string
    {
        $get = fn (string ...$aliases): string => CsvActions::value($row, ...$aliases);

        // Required identity — accept the form's labels ("Name", "SKU") plus common
        // exports ("Product name", "Sku").
        $sku = $get('SKU', 'Sku');
        $name = $get('Name', 'Product name');
        if ($sku === '' || $name === '') {
            return 'skipped';
        }

        $existing = Product::query()->where('sku', $sku)->first();

        // Unit: accept the form's "Stock unit" label plus older exports. Match on
        // code OR name so "KG" / "Piece" / "PCS" all resolve.
        $unitRef = $get('Stock unit', 'Unit', 'UOM', 'Stock Unit');
        $unit = $unitRef !== ''
            ? Unit::query()->where(fn ($q) => $q->where('code', $unitRef)->orWhere('name', $unitRef))->first()
            : null;
        // A new product must resolve a stock unit (NOT NULL); an update may keep its own.
        if ($existing === null && $unit === null) {
            return 'skipped';
        }

        // Category / Brand are looked up by name. Auto-create if missing so a bulk
        // import doesn't fail on brand-new categories/brands the user is introducing.
        $categoryName = $get('Category');
        $category = $categoryName !== '' ? Category::firstOrCreate(['name' => $categoryName]) : null;
        $brandName = $get('Brand');
        $brand = $brandName !== '' ? Brand::firstOrCreate(['name' => $brandName]) : null;

        $cost = $get('Cost price', 'CostPrice', 'Cost');
        $sell = $get('Selling price', 'SellingPrice', 'Price');
        $reorder = $get('Reorder level', 'ReorderLevel');
        $active = $get('Is active', 'Active');
        $batches = $get('Track batches', 'Tracks batch');
        $serials = $get('Track serials', 'Tracks serial');
        $barcode = $get('Barcode');

        $product = $existing ?? new Product;
        $product->fill([
            'sku' => $sku,
            'name' => $name,
            'barcode' => $barcode !== '' ? $barcode : ($existing->barcode ?? null),
            'unit_id' => $unit?->getKey() ?? $existing?->unit_id,
            'category_id' => $category?->getKey() ?? $existing?->category_id,
            'brand_id' => $brand?->getKey() ?? $existing?->brand_id,
            'cost_price' => $cost !== '' ? $cost : ($existing->cost_price ?? 0),
            'selling_price' => $sell !== '' ? $sell : ($existing->selling_price ?? 0),
            'reorder_level' => $reorder !== '' ? (int) $reorder : ($existing->reorder_level ?? null),
            'is_active' => $active !== '' ? CsvActions::bool($active) : true,
            'tracks_batch' => $batches !== '' ? CsvActions::bool($batches) : (bool) ($existing->tracks_batch ?? false),
            'tracks_serial' => $serials !== '' ? CsvActions::bool($serials) : (bool) ($existing->tracks_serial ?? false),
        ]);
        $product->save();

        return $existing !== null ? 'updated' : 'created';
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
