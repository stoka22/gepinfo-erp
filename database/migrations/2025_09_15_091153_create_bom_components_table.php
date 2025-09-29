<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bom_components', function (Blueprint $table) {
            $table->id();

            // Késztermék tétel (product)
            $table->foreignId('product_item_id')
                ->constrained('items')
                ->cascadeOnDelete();

            // Komponens tétel (alapanyag / alkatrész)
            $table->foreignId('component_item_id')
                ->constrained('items')
                ->cascadeOnDelete();

            $table->decimal('qty_per_unit', 18, 3);
            $table->string('note')->nullable();

            $table->timestamps();

            // Ugyanaz a komponens csak egyszer szerepeljen egy adott termékben
            $table->unique(['product_item_id', 'component_item_id']);

            // gyors kereséshez
            $table->index('component_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bom_components');
    }
};
