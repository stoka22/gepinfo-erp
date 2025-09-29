<?php
// database/migrations/2025_09_16_171000_create_vacation_balances_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vacation_balances', function (Blueprint $table) {
            $table->id();

            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();

            // bontásban tároljuk – összegzést accessor számolja
            $table->decimal('base_days', 5, 1)->default(0);
            $table->decimal('age_extra_days', 5, 1)->default(0);
            $table->decimal('child_extra_days', 5, 1)->default(0);
            $table->decimal('disability_extra_days', 5, 1)->default(0);
            $table->decimal('under18_extra_days', 5, 1)->default(0);

            // előző évről hozott + manuális korrekció
            $table->decimal('carried_over_days', 5, 1)->default(0);
            $table->decimal('manual_adjustment_days', 5, 1)->default(0);

            $table->timestamps();
            $table->unique(['employee_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vacation_balances');
    }
};
