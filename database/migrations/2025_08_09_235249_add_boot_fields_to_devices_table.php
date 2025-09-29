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
            $t->unsignedBigInteger('boot_seq')->default(0)->after('last_ip');
            $t->timestamp('last_boot_at')->nullable()->after('boot_seq');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('devices', function (Blueprint $t) {
            $t->dropColumn(['boot_seq','last_boot_at']);
        });
    }
};
