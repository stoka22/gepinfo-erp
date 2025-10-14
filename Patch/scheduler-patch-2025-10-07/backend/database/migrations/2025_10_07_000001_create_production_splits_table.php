<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('production_splits')) {
            return;
        }
        Schema::create('production_splits', function (Blueprint $t) {
            $t->id();
            $t->foreignId('machine_id')->constrained()->cascadeOnDelete();
            $t->foreignId('production_task_id')->nullable()->constrained()->nullOnDelete();
            $t->string('title')->nullable();
            $t->timestamp('start');
            $t->timestamp('end');
            $t->unsignedInteger('qty_total')->default(0);
            $t->unsignedInteger('qty_from')->default(0);
            $t->unsignedInteger('qty_to')->default(0);
            $t->unsignedDecimal('rate_pph', 10, 3)->nullable();
            $t->unsignedInteger('batch_size')->default(100);
            $t->string('partner_name')->nullable();
            $t->string('order_code')->nullable();
            $t->string('product_sku')->nullable();
            $t->string('operation_name')->nullable();
            $t->boolean('is_committed')->default(false);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['machine_id','start','end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_splits');
    }
};
