<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('work_logs', function (Blueprint $table) {
            $table->id();
            $table->string('nev');                // Név
            $table->string('munkakor');           // Munkakör
            $table->string('helyiseg');           // Helyiség
            $table->string('belepesi_pont')->nullable();      // Belépési Pont
            $table->dateTime('kezdes')->nullable();           // Kezdés
            $table->string('kilepesi_pont')->nullable();      // Kilépési Pont
            $table->dateTime('vege')->nullable();             // Vége
            $table->string('ido')->nullable();    // Idő (pl. "3:57")
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_logs');
    }
};
