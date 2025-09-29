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
        Schema::create('partner_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->cascadeOnUpdate(); // partners tábla
            $table->string('order_no')->unique();
            $table->date('order_date')->index();
            $table->date('due_date')->nullable()->index();
            $table->string('status')->default('draft'); // draft|confirmed|in_production|partial|completed|canceled
            $table->string('currency', 3)->default('HUF');
            $table->decimal('total_net', 14, 2)->default(0);
            $table->decimal('total_gross', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partner_orders');
    }
};
