<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('pulses', function (Blueprint $table) {
            // akkor működik az UPSERT/IGNORE, ha van unique kulcs
            $table->unique(['device_id','created_at'], 'uniq_device_minute');
        });
    }
    public function down(): void {
        Schema::table('pulses', function (Blueprint $table) {
            $table->dropUnique('uniq_device_minute');
        });
    }
};
