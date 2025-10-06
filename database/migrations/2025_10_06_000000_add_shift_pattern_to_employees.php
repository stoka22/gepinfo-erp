<?php

// database/migrations/2025_10_06_000000_add_shift_pattern_to_employees.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $t) {
            if (!Schema::hasColumn('employees', 'shift_pattern_id')) {
                $t->foreignId('shift_pattern_id')
                  ->nullable()
                  ->constrained('shift_patterns')
                  ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $t) {
            if (Schema::hasColumn('employees', 'shift_pattern_id')) {
                $t->dropConstrainedForeignId('shift_pattern_id');
            }
        });
    }
};
