<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Add the missing foreign-key indexes on the high-traffic child ("line") tables.
 * PostgreSQL does not auto-index FK columns, so joins and "lines for this
 * document" lookups were doing sequential scans. Idempotent (IF NOT EXISTS) and
 * index-only — no data or behaviour changes.
 */
return new class extends Migration
{
    /** @var array<string, list<string>> table => columns to index individually */
    private array $indexes = [
        'sales_order_lines' => ['sales_order_id', 'product_id'],
        'purchase_order_lines' => ['purchase_order_id', 'product_id'],
        'supplier_invoice_lines' => ['supplier_invoice_id'],
        'expense_lines' => ['expense_id'],
        'bom_components' => ['bill_of_materials_id'],
        'goods_receipt_lines' => ['goods_receipt_id'],
        'quotation_lines' => ['quotation_id'],
        'leave_requests' => ['employee_id'],
        'payslips' => ['employee_id'],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $table => $columns) {
            foreach ($columns as $column) {
                $name = "{$table}_{$column}_index";
                DB::statement("CREATE INDEX IF NOT EXISTS {$name} ON {$table} ({$column})");
            }
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $table => $columns) {
            foreach ($columns as $column) {
                $name = "{$table}_{$column}_index";
                DB::statement("DROP INDEX IF EXISTS {$name}");
            }
        }
    }
};
