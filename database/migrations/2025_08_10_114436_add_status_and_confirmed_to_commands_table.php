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
        Schema::table('commands', function (Blueprint $table) {
            // ha nincs még status mező:
            if (!Schema::hasColumn('commands','status')) {
                $table->string('status', 20)->default('pending')->index();
            }
            // új mező a reboot utólagos megerősítéséhez
            $table->boolean('confirmed')->default(false)->after('status')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commands', function (Blueprint $table) {
            if (Schema::hasColumn('commands','confirmed')) {
                $table->dropColumn('confirmed');
            }
            // csak akkor dobd vissza, ha te tetted be
            // $table->dropColumn('status');
        });
    }
};
