<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Napi kötelező munkaidő órában (pl. 4.00 / 6.00 / 8.00) — a túlóra-motor
            // ebből (+ 30 perc puffer, + 10 perc türelmi idő) számolja a napi túlóra-küszöböt
            // az adott dolgozóra, nem egy mindenkire fix 8:30-cal.
            $table->decimal('daily_quota_hours', 4, 2)->default(8.00)->after('employment_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('daily_quota_hours');
        });
    }
};
