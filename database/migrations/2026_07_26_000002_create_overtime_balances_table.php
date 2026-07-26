<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overtime_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();

            // Folyamatosan göngyölt egyenleg percben; lehet negatív (ld. app/Services/Overtime/OvertimeBalanceService).
            $table->integer('balance_minutes')->default(0);
            // Admin általi manuális korrekció, elkülönítve az automatikus elszámolástól (audit célra).
            $table->integer('manual_adjustment_minutes')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_balances');
    }
};
