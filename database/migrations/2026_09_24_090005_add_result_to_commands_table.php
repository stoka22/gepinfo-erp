<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Latens hiba javítása: a Command::ack() logika (routes/api.php és a törölt
// DeviceApiController) mindig $cmd->result = [...] -t próbált menteni, de ez
// az oszlop soha nem létezett -- a mentés végzetes SQL hibával elszállt volna.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commands', function (Blueprint $t) {
            $t->json('result')->nullable()->after('confirmed');
        });
    }

    public function down(): void
    {
        Schema::table('commands', function (Blueprint $t) {
            $t->dropColumn('result');
        });
    }
};
