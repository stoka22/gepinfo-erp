<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $t) {
            $t->json('meta')->nullable()->after('rollback_url');
            $t->string('api_key_hash')->nullable()->after('device_token');
            $t->enum('platform', ['esp32', 'esp8266'])->nullable()->after('api_key_hash');
        });

        // A device_token-t csak a törölt hello/hello-auth endpointok használták --
        // az új enroll/push kontraktban az api_key_hash veszi át a szerepét.
        Schema::table('devices', function (Blueprint $t) {
            $t->dropUnique(['device_token']);
            $t->dropColumn('device_token');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $t) {
            $t->string('device_token')->nullable()->unique();
        });

        Schema::table('devices', function (Blueprint $t) {
            $t->dropColumn(['meta', 'api_key_hash', 'platform']);
        });
    }
};
