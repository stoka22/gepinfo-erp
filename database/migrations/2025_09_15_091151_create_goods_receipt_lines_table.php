<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Feltételezi, hogy a goods_receipts és items táblák már léteznek.
        Schema::table('goods_receipt_lines', function (Blueprint $table) {
            $table->id();

            $table->foreignId('goods_receipt_id')
                ->constrained('goods_receipts')
                ->cascadeOnDelete();

            $table->foreignId('item_id')
                ->constrained('items')
                ->cascadeOnDelete();

            $table->decimal('qty', 18, 3);
            $table->decimal('unit_cost', 18, 4);
            $table->decimal('line_total', 18, 2);

            $table->string('note')->nullable();

            $table->timestamps();

            // Gyakoribb keresésekhez
            $table->index(['goods_receipt_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_lines');
    }
};
