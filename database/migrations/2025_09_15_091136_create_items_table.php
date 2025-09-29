<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('sku')->nullable()->index();
            $table->string('name')->index();
            $table->string('unit', 16)->default('db'); // db, kg, m, stb.
            $table->enum('kind', ['alkatresz','alapanyag','kesztermek'])->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['company_id','sku']);
        });
    }
    public function down(): void { Schema::dropIfExists('items'); }
};
