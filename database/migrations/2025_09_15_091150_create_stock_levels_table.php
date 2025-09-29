<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('stock_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->decimal('qty', 18, 3)->default(0);
            $table->decimal('avg_cost', 18, 4)->default(0); // egységár HUF-ban
            $table->timestamps();
            $table->unique(['company_id','warehouse_id','item_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('stock_levels'); }
};
