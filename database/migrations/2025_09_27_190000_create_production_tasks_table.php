<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('production_tasks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $t->foreignId('partner_order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('partner_order_item_id')->constrained()->cascadeOnDelete();
            $t->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $t->foreignId('workflow_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('item_work_step_id')->nullable()->constrained('item_work_steps')->nullOnDelete();
            $t->foreignId('machine_id')->nullable()->constrained()->nullOnDelete();
            $t->decimal('qty', 14, 3)->default(0);
            $t->unsignedInteger('setup_seconds')->default(0);
            $t->unsignedInteger('run_seconds')->default(0);
            $t->timestamp('starts_at')->nullable()->index();
            $t->timestamp('ends_at')->nullable()->index();
            $t->string('status')->default('planned'); // planned|in_progress|done|blocked|canceled
            $t->string('note')->nullable();
            $t->timestamps();
            $t->index(['machine_id','starts_at','ends_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('production_tasks'); }
};
