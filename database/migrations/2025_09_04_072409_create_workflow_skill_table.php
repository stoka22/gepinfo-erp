<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('workflow_skill', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('required_level')->default(1); // 1..5
            $table->timestamps();

            $table->unique(['workflow_id', 'skill_id']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('workflow_skill');
    }
};
