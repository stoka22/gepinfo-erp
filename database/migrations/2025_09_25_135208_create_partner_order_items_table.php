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
        Schema::create('partner_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnUpdate(); // késztermék (items)
            $table->string('item_name_cache'); // gyors listázáshoz
            $table->string('unit')->default('db');
            $table->decimal('qty_ordered', 14, 3);
            $table->decimal('qty_reserved', 14, 3)->default(0);   // készletfoglalás
            $table->decimal('qty_produced', 14, 3)->default(0);   // elkészült mennyiség (splits összeg)
            $table->decimal('qty_shipped', 14, 3)->default(0);    // ha lesz szállítási modul
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->date('due_date')->nullable();
            $table->string('status')->default('open'); // open|partial|fulfilled|canceled
            $table->timestamps();
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partner_order_items');
    }
};
