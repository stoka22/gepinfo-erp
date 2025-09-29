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
        Schema::create('shift_patterns', function (Blueprint $t) {
            $t->id();
            $t->string('name'); // pl. délelőtt
            $t->unsignedTinyInteger('dow'); // 0=vasárnap ... 6=szombat
            $t->time('start_time');
            $t->time('end_time'); // ha éjfél után, kezeld külön logikával
            $t->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shift_patterns');
    }
};
