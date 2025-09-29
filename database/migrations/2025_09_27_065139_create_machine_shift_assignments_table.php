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
        Schema::create('machine_shift_assignments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('machine_id')->constrained()->cascadeOnDelete();
            $t->foreignId('shift_pattern_id')->constrained()->cascadeOnDelete();
            $t->date('valid_from');
            $t->date('valid_to')->nullable();
            $t->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('machine_shift_assignments');
    }
};
