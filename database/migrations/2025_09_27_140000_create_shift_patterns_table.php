<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
         if (!Schema::hasTable('shift_patterns')) {
            Schema::create('shift_patterns', function (Blueprint $t) {
                $t->id();
                $t->string('name');          // pl. Délelőtt
                $t->unsignedTinyInteger('dow'); // 0=vasárnap ... 6=szombat
                $t->time('start_time');      // 06:00:00
                $t->time('end_time');        // 14:00:00 (ha éjfél után zár, külön kezeled a kódban)
                $t->timestamps();

                $t->index(['dow']);
            });
        }
    }
    public function down(): void {
        Schema::dropIfExists('shift_patterns');
    }
};
