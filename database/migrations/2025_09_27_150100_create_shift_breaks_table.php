<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('shift_breaks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('shift_pattern_id')->constrained('shift_patterns')->cascadeOnDelete();
            $t->string('name')->nullable();          // pl. Reggeli, Ebéd
            $t->time('start_time');                  // a műszakon BELÜLI idő
            $t->unsignedInteger('duration_min');    // perc
            $t->timestamps();
            $t->index(['shift_pattern_id']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('shift_breaks');
    }
};
