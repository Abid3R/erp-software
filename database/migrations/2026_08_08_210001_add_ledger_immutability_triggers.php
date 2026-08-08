<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Database-level immutability for the two authoritative ledgers (spec #10, #14),
 * as defense in depth behind the application guards. The restricted erp_app role
 * cannot disable these triggers, so posted financial history and inventory
 * movements cannot be altered through the application (see ACCOUNTING.md).
 *
 *  - journals / journal_lines: no UPDATE or DELETE once the journal is posted.
 *  - inventory_transactions: append-only; never UPDATE or DELETE.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION erp_prevent_posted_journal_change() RETURNS trigger AS $$
            BEGIN
                IF OLD.status = 'posted' THEN
                    RAISE EXCEPTION 'Posted journals are immutable (%). Correct via a reversal entry.', TG_OP
                        USING ERRCODE = 'check_violation';
                END IF;
                IF TG_OP = 'DELETE' THEN RETURN OLD; ELSE RETURN NEW; END IF;
            END;
            $$ LANGUAGE plpgsql;

            CREATE OR REPLACE FUNCTION erp_prevent_posted_line_change() RETURNS trigger AS $$
            DECLARE journal_status text;
            BEGIN
                SELECT status INTO journal_status FROM journals
                    WHERE id = COALESCE(OLD.journal_id, NEW.journal_id);
                IF journal_status = 'posted' THEN
                    RAISE EXCEPTION 'Lines of a posted journal are immutable.'
                        USING ERRCODE = 'check_violation';
                END IF;
                IF TG_OP = 'DELETE' THEN RETURN OLD; ELSE RETURN NEW; END IF;
            END;
            $$ LANGUAGE plpgsql;

            CREATE OR REPLACE FUNCTION erp_prevent_inventory_change() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'Inventory ledger rows are immutable (%).', TG_OP
                    USING ERRCODE = 'check_violation';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER trg_journals_immutable
                BEFORE UPDATE OR DELETE ON journals
                FOR EACH ROW EXECUTE FUNCTION erp_prevent_posted_journal_change();

            CREATE TRIGGER trg_journal_lines_immutable
                BEFORE UPDATE OR DELETE ON journal_lines
                FOR EACH ROW EXECUTE FUNCTION erp_prevent_posted_line_change();

            CREATE TRIGGER trg_inventory_transactions_immutable
                BEFORE UPDATE OR DELETE ON inventory_transactions
                FOR EACH ROW EXECUTE FUNCTION erp_prevent_inventory_change();
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS trg_journals_immutable ON journals;
            DROP TRIGGER IF EXISTS trg_journal_lines_immutable ON journal_lines;
            DROP TRIGGER IF EXISTS trg_inventory_transactions_immutable ON inventory_transactions;
            DROP FUNCTION IF EXISTS erp_prevent_posted_journal_change();
            DROP FUNCTION IF EXISTS erp_prevent_posted_line_change();
            DROP FUNCTION IF EXISTS erp_prevent_inventory_change();
        SQL);
    }
};
