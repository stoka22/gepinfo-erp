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
               // tiltott / karbantartási sávok (M7-hez is kell)
        Schema::create('machine_blockings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('machine_id')->constrained()->cascadeOnDelete();
            $t->dateTime('starts_at');
            $t->dateTime('ends_at')->nullable();
            $t->string('reason')->nullable(); // pl. karbantartás
            $t->timestamps();
            $t->index(['machine_id','starts_at','ends_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resource_blockings');
    }
};
