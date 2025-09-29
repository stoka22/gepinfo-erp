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
        Schema::create('item_work_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->index();       // ha multitenant
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->unsignedInteger('step_no')->default(1);             // sorrend
            $table->string('name');                                     // művelet neve
            $table->foreignId('machine_id')->nullable()->constrained('machines')->nullOnDelete(); // ha van machines tábla
            $table->decimal('cycle_time_sec', 10, 3);                   // ciklusidő mp/db
            $table->decimal('setup_time_sec', 10, 3)->default(0);       // beállási idő mp (fix)
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['item_id', 'step_no']);
            $table->index(['item_id', 'is_active']);
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_work_steps');
    }
};
