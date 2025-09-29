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
        Schema::create('production_splits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_order_item_id')->constrained()->cascadeOnDelete();
            $table->decimal('qty', 14, 3);        // ebben a részletben gyártott mennyiség
            $table->date('produced_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_splits');
    }
};
