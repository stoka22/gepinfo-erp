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
        Schema::table('devices', function (Blueprint $t) {
            $t->string('fw_version')->nullable()->after('location');
            $t->string('ssid')->nullable()->after('fw_version');
            $t->integer('rssi')->nullable()->after('ssid');
            $t->timestamp('last_seen_at')->nullable()->after('rssi');
            $t->string('last_ip')->nullable()->after('last_seen_at');

            // OTA-hoz opcionális mezők:
            $t->string('ota_channel')->nullable()->after('last_ip'); // pl. stable/beta
            $t->string('rollback_url')->nullable()->after('ota_channel'); // előző FW URL
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('devices', function (Blueprint $t) {
            $t->dropColumn(['fw_version','ssid','rssi','last_seen_at','last_ip','ota_channel','rollback_url']);
        });
    }
};
