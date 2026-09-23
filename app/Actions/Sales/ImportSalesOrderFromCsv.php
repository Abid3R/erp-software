<?php

namespace App\Actions\Sales;

use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Unit;
use App\Support\DocumentNumber;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Builds a sales order from an uploaded order CSV (the buyer "CO upload file"
 * format). Each row is one order line: Item Name (fabric family), Item Code, Style
 * / PO, Composition, GSM, Width, Colour, QTY and Unit Price. A product is found or
 * created per Item Code + Style + Colour (so each colour/style is its own
 * self-describing finished-fabric SKU with its spec), and one draft sales order is
 * created with a line per row. Reuses the normal Product + Sales Order engine — no
 * separate import store. Atomic.
 */
class ImportSalesOrderFromCsv
{
    /** Header aliases → canonical field. Headers are matched case-insensitively with any [..] suffix stripped. */
    private const HEADERS = [
        'item name' => 'family', 'item code' => 'code', 'style / po' => 'style', 'style' => 'style',
        'composition' => 'composition', 'gsm' => 'gsm', 'width' => 'width', 'size' => 'size',
        'color / code' => 'colour', 'color' => 'colour', 'colour' => 'colour',
        'qty' => 'qty', 'quantity' => 'qty', 'qty unit' => 'unit', 'unit price' => 'price', 'rate' => 'price',
    ];

    /**
     * @param  array{customer_id: int, warehouse_id: int, order_date?: ?string, delivery_date?: ?string, notes?: ?string}  $meta
     */
    public function handle(string $csv, array $meta): SalesOrder
    {
        $rows = $this->parse($csv);
        if ($rows === []) {
            throw new RuntimeException('No valid order lines found in the CSV.');
        }

        $kg = Unit::query()->where('code', 'KG')->first() ?? Unit::query()->first();

        return DB::transaction(function () use ($rows, $meta, $kg): SalesOrder {
            $order = SalesOrder::create([
                'so_number' => DocumentNumber::next('sales_order', 'SO-', SalesOrder::query()->count()),
                'customer_id' => $meta['customer_id'],
                'warehouse_id' => $meta['warehouse_id'],
                'order_date' => $meta['order_date'] ?? now()->toDateString(),
                'delivery_date' => $meta['delivery_date'] ?? null,
                'status' => 'draft',
                'notes' => $meta['notes'] ?? 'Imported from CSV',
            ]);

            foreach ($rows as $r) {
                $product = $this->resolveProduct($r, (int) ($kg?->getKey()));
                $order->lines()->create([
                    'product_id' => $product->getKey(),
                    'quantity_ordered' => (string) $r['qty'],
                    'unit_price' => (string) $r['price'],
                ]);
            }

            return $order->refresh();
        });
    }

    /**
     * Parse the CSV into normalised line rows. Skips the header, blank rows, and a
     * secondary "field code" row (one whose QTY is not numeric).
     *
     * @return list<array<string, mixed>>
     */
    public function parse(string $csv): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($csv)) ?: [];
        if (count($lines) < 2) {
            return [];
        }

        $header = array_map(fn (string $h): string => $this->normaliseHeader($h), str_getcsv(array_shift($lines)));
        $map = [];
        foreach ($header as $i => $h) {
            if (isset(self::HEADERS[$h])) {
                $map[self::HEADERS[$h]] = $i;
            }
        }
        if (! isset($map['code']) && ! isset($map['family'])) {
            throw new RuntimeException('CSV must have an "Item Code" (or "Item Name") column.');
        }
        if (! isset($map['qty'])) {
            throw new RuntimeException('CSV must have a "QTY" column.');
        }

        $get = fn (array $cols, string $key): string => isset($map[$key], $cols[$map[$key]]) ? trim((string) $cols[$map[$key]]) : '';

        $rows = [];
        foreach ($lines as $line) {
            if (trim($line) === '' || trim(str_replace(',', '', $line)) === '') {
                continue;
            }
            $cols = str_getcsv($line);
            $qtyRaw = $get($cols, 'qty');
            if (! is_numeric($qtyRaw) || (float) $qtyRaw <= 0) {
                continue; // header-code row or blank/invalid line
            }
            $code = $get($cols, 'code');
            $family = $get($cols, 'family');
            if ($code === '' && $family === '') {
                continue;
            }
            $priceRaw = $get($cols, 'price');
            $rows[] = [
                'family' => $family, 'code' => $code, 'style' => $get($cols, 'style'),
                'composition' => $get($cols, 'composition'), 'gsm' => $get($cols, 'gsm'),
                'width' => $get($cols, 'width'), 'colour' => $get($cols, 'colour'),
                'qty' => (string) BigDecimal::of($qtyRaw),
                'price' => is_numeric($priceRaw) ? (string) BigDecimal::of($priceRaw) : '0',
            ];
        }

        return $rows;
    }

    private function normaliseHeader(string $h): string
    {
        $h = preg_replace('/\[[^\]]*\]/', '', $h) ?? $h; // strip [10], [20], …
        $h = preg_replace('/\s+/', ' ', (string) $h) ?? $h;

        return trim(strtolower($h));
    }

    /** Find or create the finished-fabric product for a row (SKU = code + style + colour). */
    private function resolveProduct(array $r, int $unitId): Product
    {
        $base = $r['code'] !== '' ? $r['code'] : Str::of($r['family'])->slug()->upper()->value();
        $sku = collect([$base, $r['style'], $r['colour']])
            ->filter(fn ($v): bool => filled($v))
            ->map(fn ($v): string => Str::of((string) $v)->upper()->replaceMatches('/[^A-Z0-9]+/', '')->value())
            ->implode('-');

        $name = trim(($r['family'] ?: $r['code'])
            .($r['style'] ? ' — '.$r['style'] : '')
            .($r['colour'] ? ' — '.$r['colour'] : '')
            .($r['gsm'] ? ' ('.$r['gsm'].' GSM)' : ''));

        $isTextile = filled($r['family']) || filled($r['gsm']) || filled($r['colour']);

        return Product::updateOrCreate(
            ['sku' => $sku],
            array_filter([
                'unit_id' => $unitId,
                'name' => $name !== '' ? $name : $sku,
                'is_active' => true,
                'is_textile' => $isTextile,
                'textile_type' => $isTextile ? 'finished_fabric' : null,
                'fabric_type' => $r['family'] ?: null,
                'gsm' => $r['gsm'] ?: null,
                'width' => $r['width'] ?: null,
                'colour' => $r['colour'] ?: null,
                'style' => $r['style'] ?: null,
                'construction' => $r['composition'] ?: null,
                'is_roll_tracked' => $isTextile,
            ], fn ($v): bool => $v !== null),
        );
    }
}
