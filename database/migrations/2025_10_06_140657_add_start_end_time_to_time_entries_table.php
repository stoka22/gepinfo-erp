<?php

// database/migrations/xxxx_xx_xx_xxxxxx_add_start_end_time_to_time_entries_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->time('start_time')->nullable()->after('start_date');
            $table->time('end_time')->nullable()->after('end_date');

            // (opcionális) kis gyorsítás a listázás/szűrés miatt
            $table->index(['employee_id', 'start_date'], 'te_emp_start_idx');
        });
    }

    public function down(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->dropIndex('te_emp_start_idx');
            $table->dropColumn(['start_time', 'end_time']);
        });
    }
};

