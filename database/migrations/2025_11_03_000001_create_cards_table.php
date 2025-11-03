<?php
// database/migrations/2025_11_03_000001_create_cards_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('cards')) {
            Schema::create('cards', function (Blueprint $t) {
                $t->id();
                $t->string('uid')->unique();          // kötelező, egyedi
                $t->string('label')->nullable();      // pl. “kék kártya”
                $t->enum('status', ['available','assigned','lost','blocked'])
                  ->default('available')->index();

                // Jelenlegi (pillanatnyi) hozzárendelés – 1-1 kapcsolat
                $t->foreignId('employee_id')->nullable()
                  ->constrained('employees')->nullOnDelete()->cascadeOnUpdate();
                $t->timestamp('assigned_at')->nullable();

                $t->text('notes')->nullable();
                $t->timestamps();
                $t->softDeletes();

                // Egy dolgozóhoz egyszerre csak egy kártya rendelhető
                $t->unique('employee_id'); // több NULL engedélyezett (oké)
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cards');
    }
};
