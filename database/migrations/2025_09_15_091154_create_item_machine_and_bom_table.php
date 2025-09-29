<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('item_machine')) {
            Schema::create('item_machine', function (Blueprint $table) {
                $table->id();
                $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
                $table->foreignId('machine_id')->constrained('machines')->cascadeOnDelete();
                $table->string('note')->nullable();
                $table->timestamps();
                $table->unique(['item_id', 'machine_id']);
            });
        }

        if (! Schema::hasTable('bom_components')) {
            Schema::create('bom_components', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_item_id')->constrained('items')->cascadeOnDelete();
                $table->foreignId('component_item_id')->constrained('items')->cascadeOnDelete();
                $table->decimal('qty_per_unit', 18, 3);
                $table->string('note')->nullable();
                $table->timestamps();
                $table->unique(['product_item_id', 'component_item_id']);
                $table->index('component_item_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('item_machine')) {
            Schema::drop('item_machine');
        }
        if (Schema::hasTable('bom_components')) {
            Schema::drop('bom_components');
        }
    }
};
