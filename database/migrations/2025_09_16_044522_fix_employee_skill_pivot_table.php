<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1) PK + id egyszerre törölve, hogy ne maradjon auto_increment kulcs nélkül
        DB::statement('ALTER TABLE `employee_skill` DROP PRIMARY KEY, DROP COLUMN `id`');

        // 2) Kompozit egyediség (klasszikus pivot viselkedés)
        Schema::table('employee_skill', function (Blueprint $table) {
            $table->unique(['employee_id', 'skill_id'], 'employee_skill_unique');
        });
    }

    public function down(): void
    {
        // visszaállítás: előbb az egyedi indexet dobjuk
        Schema::table('employee_skill', function (Blueprint $table) {
            $table->dropUnique('employee_skill_unique');
        });

        // majd visszarakjuk az id AUTO_INCREMENT PK-t
        DB::statement('ALTER TABLE `employee_skill` ADD `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST');
    }
};
