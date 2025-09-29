<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('shift_patterns', function (Blueprint $t) {
            // 7 bites maszk: bit0=Vas ... bit6=Szo
            $t->unsignedTinyInteger('days_mask')->default(0b0111110) // alap: H–P
              ->after('name');
            $t->dropColumn('dow'); // ha már létezett az egynapos mező
        });
    }
    public function down(): void {
        Schema::table('shift_patterns', function (Blueprint $t) {
            $t->unsignedTinyInteger('dow')->nullable();
            $t->dropColumn('days_mask');
        });
    }
};
