<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_imports', function (Blueprint $table) {
            $table->id();
            $table->string('source')->nullable();     // pl. fájlnév
            $table->timestamps();
        });

        Schema::create('card_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_import_id')->constrained('card_imports')->cascadeOnDelete();
            $table->string('raw_name')->nullable();
            $table->string('raw_uid', 191)->index();
            $table->string('raw_company')->nullable();
            $table->unsignedBigInteger('matched_employee_id')->nullable()->index();
            $table->float('match_score')->nullable();      // 0..100
            $table->enum('status', ['new','auto','ambiguous','linked','skipped','duplicate'])->default('new');
            $table->json('meta')->nullable();              // bármi extra oszlop
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_import_rows');
        Schema::dropIfExists('card_imports');
    }
};
