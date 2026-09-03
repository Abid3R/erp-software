<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Indexes for the manufacturing queries — the MRP planner and dashboards filter
 * manufacturing_orders by status and product. Index-only, idempotent.
 */
return new class extends Migration
{
    /** @var array<string, list<string>> */
    private array $indexes = [
        'manufacturing_orders' => ['status', 'product_id'],
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
                DB::statement("DROP INDEX IF EXISTS {$table}_{$column}_index");
            }
        }
    }
};
