<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The default Laravel notifications migration stores `data` as text, but Filament's
 * database notifications query it with the PostgreSQL JSON operator `->>`, which
 * only works on json/jsonb. Convert the column in place (existing rows hold valid
 * JSON, so the cast is safe). No-op if already jsonb.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE jsonb USING data::jsonb');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE text USING data::text');
        }
    }
};
