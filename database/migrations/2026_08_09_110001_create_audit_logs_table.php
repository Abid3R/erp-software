<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Immutable audit trail (spec #30): who did what to which record, with before/
 * after values, IP, and context. Append-only — a DB trigger blocks UPDATE and
 * DELETE so ordinary users (and the application) cannot alter the trail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 32);                 // created / updated / deleted / custom
            $table->morphs('auditable');
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('url')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('company_id');
            $table->index(['user_id', 'created_at']);
        });

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION erp_prevent_audit_change() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'Audit log is append-only (%).', TG_OP
                    USING ERRCODE = 'check_violation';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER trg_audit_logs_append_only
                BEFORE UPDATE OR DELETE ON audit_logs
                FOR EACH ROW EXECUTE FUNCTION erp_prevent_audit_change();
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_audit_logs_append_only ON audit_logs;');
        DB::unprepared('DROP FUNCTION IF EXISTS erp_prevent_audit_change();');
        Schema::dropIfExists('audit_logs');
    }
};
