<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('firmwares', function (Blueprint $t) {
            $t->string('md5', 32)->nullable()->after('sha256');
            // default 'esp32' a már esetleg feltöltött rekordok miatt -- minden ÚJ
            // feltöltésnél a form kötelezővé teszi a tényleges kiválasztást.
            $t->enum('platform', ['esp32', 'esp8266'])->default('esp32')->after('hardware_code');
        });
    }

    public function down(): void
    {
        Schema::table('firmwares', function (Blueprint $t) {
            $t->dropColumn(['md5', 'platform']);
        });
    }
};
