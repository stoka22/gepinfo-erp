<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('production_logs', function (Blueprint $table) {
            $table->unique(['machine_id', 'created_at'], 'uniq_machine_minute');
        });
    }
    public function down(): void {
        Schema::table('production_logs', function (Blueprint $table) {
            $table->dropUnique('uniq_machine_minute');
        });
    }
};
