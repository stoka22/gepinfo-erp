<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_partner_id')->nullable()->constrained('partners')->nullOnDelete();
            $table->string('receipt_no')->nullable()->index();
            $table->date('occurred_at')->index();
            $table->string('currency', 8)->default('HUF');
            $table->text('note')->nullable();
            $table->timestamp('posted_at')->nullable(); // könyvelve?
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id','receipt_no']);
        });

        Schema::create('goods_receipt_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->decimal('qty', 18, 3);
            $table->decimal('unit_cost', 18, 4); // beszerzési egységár (HUF)
            $table->decimal('line_total', 18, 2); // qty * unit_cost
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('goods_receipt_lines');
        Schema::dropIfExists('goods_receipts');
    }
};
