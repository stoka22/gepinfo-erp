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
        Schema::create('pulses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('device_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('sample_id')->index(); // idempotencia
            $t->timestamp('sample_time')->index();
            $t->unsignedBigInteger('count'); // kumulált
            $t->unsignedBigInteger('delta'); // számolt különbség
            $t->timestamps();
            $t->unique(['device_id','sample_id']); // duplikáció ellen
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pulses');
    }
};
