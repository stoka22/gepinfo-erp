<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('partner_order_items', function (Blueprint $table) {
            // Választhatsz: nullable VAGY default('')
            $table->string('item_name_cache')->default('')->change();
            // vagy: $table->string('item_name_cache')->nullable()->change();
        });
    }
    public function down(): void
    {
        Schema::table('partner_order_items', function (Blueprint $table) {
            $table->string('item_name_cache')->nullable(false)->default(null)->change();
        });
    }
};

