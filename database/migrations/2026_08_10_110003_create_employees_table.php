<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Employee master (spec #24 HR). Company-scoped, self-referencing reporting line,
 * optionally linked to a login user. base_salary anchors later payroll.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('employee_code', 32);
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('designation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('employment_type', 16)->default('permanent');
            $table->string('status', 16)->default('active');
            $table->date('join_date');
            $table->date('termination_date')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 10)->nullable();
            $table->string('address')->nullable();
            $table->decimal('base_salary', 19, 2)->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'employee_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
