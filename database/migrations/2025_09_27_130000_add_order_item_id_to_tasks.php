<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $t) {
            $t->foreignId('order_item_id')
              ->nullable()
              ->after('machine_id')
              ->constrained('partner_order_items')
              ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $t) {
            $t->dropConstrainedForeignId('order_item_id');
        });
    }
};
