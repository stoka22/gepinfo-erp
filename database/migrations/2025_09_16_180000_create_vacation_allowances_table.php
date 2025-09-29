<?php
// database/migrations/2025_09_16_180000_create_vacation_allowances_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vacation_allowances', function (Blueprint $table) {
            $table->id();

            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year')->index();
            $table->string('type', 32)->index(); // App\Enums\VacationAllowanceType
            $table->decimal('days', 5, 1)->default(0);
            $table->string('note', 255)->nullable();

            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id','year','type'], 'va_emp_year_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vacation_allowances');
    }
};
