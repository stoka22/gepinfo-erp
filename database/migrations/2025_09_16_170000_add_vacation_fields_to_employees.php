<?php
// database/migrations/2025_09_16_170000_add_vacation_fields_to_employees.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (!Schema::hasColumn('employees', 'birth_date')) {
                $table->date('birth_date')->nullable()->after('hired_at');
            }
            if (!Schema::hasColumn('employees', 'children_under_16')) {
                $table->unsignedTinyInteger('children_under_16')->default(0)->after('birth_date');
            }
            if (!Schema::hasColumn('employees', 'is_disabled')) {
                $table->boolean('is_disabled')->default(false)->after('children_under_16');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (Schema::hasColumn('employees', 'is_disabled')) $table->dropColumn('is_disabled');
            if (Schema::hasColumn('employees', 'children_under_16')) $table->dropColumn('children_under_16');
            if (Schema::hasColumn('employees', 'birth_date')) $table->dropColumn('birth_date');
        });
    }
};
