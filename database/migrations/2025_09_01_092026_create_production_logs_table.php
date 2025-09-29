<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('production_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('qty')->default(0);
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void {
        Schema::dropIfExists('production_logs');
    }
};
