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
        Schema::create('task_dependencies', function (Blueprint $t) {
            $t->id();
            $t->foreignId('predecessor_id')->constrained('tasks')->cascadeOnDelete();
            $t->foreignId('successor_id')->constrained('tasks')->cascadeOnDelete();
            $t->enum('type', ['FS'])->default('FS'); // első körben csak FS
            $t->integer('lag_minutes')->default(0);  // lehet negatív is, ha szeretnéd
            $t->timestamps();

            $t->unique(['predecessor_id','successor_id']);
            $t->index(['predecessor_id']);
            $t->index(['successor_id']);
            // opcionális: self-dependency tiltása app szinten validációval
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_dependencies');
    }
};
