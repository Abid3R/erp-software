<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-company notification preferences. Controls which operational alerts the
 * system raises (leave approvals, overdue receivables, low stock) and the
 * overdue-days threshold. One row per company; absence of a row means every
 * alert is on (safe default).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('leave_approvals_enabled')->default(true);
            $table->boolean('overdue_invoices_enabled')->default(true);
            $table->boolean('low_stock_enabled')->default(true);
            $table->unsignedSmallInteger('overdue_days')->default(30); // receivables older than this are "overdue"
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_settings');
    }
};
