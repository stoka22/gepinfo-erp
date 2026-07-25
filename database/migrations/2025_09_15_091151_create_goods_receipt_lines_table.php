<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // A goods_receipt_lines táblát a create_goods_receipts_table migráció már létrehozza;
        // ez a migráció csak a gyakori kereséshez használt összetett indexet pótolja.
        Schema::table('goods_receipt_lines', function (Blueprint $table) {
            $table->index(['goods_receipt_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipt_lines', function (Blueprint $table) {
            $table->dropIndex(['goods_receipt_id', 'item_id']);
        });
    }
};
