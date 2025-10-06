<?php

// database/migrations/xxxx_add_company_to_workflows.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('workflows', function (Blueprint $t) {
            $t->foreignId('company_id')
              ->nullable() // ha vannak régi adatok
              ->constrained()->cascadeOnDelete();
            $t->index('company_id');
        });
    }
    public function down(): void {
        Schema::table('workflows', function (Blueprint $t) {
            $t->dropConstrainedForeignId('company_id');
        });
    }
};
