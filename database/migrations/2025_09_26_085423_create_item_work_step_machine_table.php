<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('item_work_step_machine', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_work_step_id')->constrained('item_work_steps')->cascadeOnDelete();
            $table->foreignId('machine_id')->constrained('machines')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['item_work_step_id','machine_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_work_step_machine');
    }
};

