<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('devices', function (Blueprint $table) {
            $table->boolean('cron_enabled')->default(false)->after('device_token');
        });
    }
    public function down(): void {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn('cron_enabled');
        });
    }
};
