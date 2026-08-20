<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extend warehouses with optional contact details so operators can maintain
 * multiple physical stores (factory, raw-material store, retail, transit, etc.)
 * without hard-coded types — all data is captured as regular warehouse records.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->string('address')->nullable()->after('code');
            $table->string('contact_person')->nullable()->after('address');
            $table->string('phone', 64)->nullable()->after('contact_person');
        });
    }

    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropColumn(['address', 'contact_person', 'phone']);
        });
    }
};
