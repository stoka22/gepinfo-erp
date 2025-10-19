<?php

// database/migrations/xxxx_add_channel_total_to_pulses.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('pulses', function (Blueprint $t) {
            if (!Schema::hasColumn('pulses','sample_time'))   $t->dateTime('sample_time')->index();
            foreach (['d1','d2','d3','d4'] as $k) {
                if (!Schema::hasColumn('pulses', $k.'_delta')) $t->unsignedBigInteger($k.'_delta')->default(0);
                if (!Schema::hasColumn('pulses', $k.'_total')) $t->unsignedBigInteger($k.'_total')->default(0);
            }
            // Régi oszlopok megmaradhatnak kompat miatt: sample_id, count, delta (nem kötelező használni)
            $t->unique(['device_id','sample_time']); // percenként 1 sor / eszköz
        });
    }
    public function down(): void {
        Schema::table('pulses', function (Blueprint $t) {
            // opcionális visszaállítás
        });
    }
};
