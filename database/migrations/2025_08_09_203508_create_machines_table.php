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
        Schema::create('machines', function (Blueprint $t) {
            $t->id();
            $t->string('name');                 // gép neve (pl. CNC-1)
            $t->string('code')->unique();       // belső azonosító (pl. GEP-001)
            $t->string('location')->nullable(); // telephely/műhely
            $t->string('vendor')->nullable();   // gyártó
            $t->string('model')->nullable();    // típus
            $t->string('serial')->nullable();   // gyári szám
            $t->date('commissioned_at')->nullable(); // üzembe helyezés
            $t->boolean('active')->default(true);
            $t->text('notes')->nullable();
            $t->timestamps();
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('machines');
    }
};
