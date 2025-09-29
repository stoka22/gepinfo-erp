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
       Schema::create('machine_calendars', function (Blueprint $t) {
            $t->id();
            $t->foreignId('machine_id')->constrained()->cascadeOnDelete();
            $t->date('work_date');
            $t->unsignedInteger('capacity_minutes');
            $t->timestamps();
            $t->unique(['machine_id','work_date']);
        });

 
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('machine_calendars');
    }
};
