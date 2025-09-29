<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('resource_shift_assignments', function (Blueprint $t) {
            $t->id();
            // NÁLUNK a "resource" = gép, ezért a géptáblára mutat
            $t->foreignId('resource_id')->constrained('machines')->cascadeOnDelete();
            $t->foreignId('shift_pattern_id')->constrained('shift_patterns')->cascadeOnDelete();

            $t->date('valid_from');       // érvényesség kezdete
            $t->date('valid_to')->nullable(); // ha null, akkor nyílt vég
            $t->timestamps();

            $t->index(['resource_id','valid_from']);
            $t->index(['shift_pattern_id']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('resource_shift_assignments');
    }
};
