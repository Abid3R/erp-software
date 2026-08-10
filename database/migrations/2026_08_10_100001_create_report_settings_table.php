<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-company report/voucher presentation (spec #31): logo, letterhead, footer and
 * signatory labels differ by company, so an Editor can brand documents without code
 * changes. One row per company.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('show_logo')->default(true);
            $table->string('logo_path')->nullable();
            $table->string('header_note')->nullable();       // line under the company name (address/tagline)
            $table->string('footer_note')->nullable();       // footer line on every document
            $table->string('signatory_left')->nullable();    // e.g. "Prepared by"
            $table->string('signatory_right')->nullable();   // e.g. "Authorised signature"
            $table->text('terms')->nullable();               // voucher terms / notes
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_settings');
    }
};
