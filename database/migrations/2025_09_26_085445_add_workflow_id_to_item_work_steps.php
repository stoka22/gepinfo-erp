<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('item_work_steps', function (Blueprint $table) {
            // ha volt single machine_id oszlopod, hagyjuk meg nullable-ként kompatibilitás miatt
            $table->foreignId('workflow_id')
                ->nullable()
                ->after('name')
                ->constrained('workflows')
                ->nullOnDelete();

            // ha a name-t workflow-ból fogod tölteni, lehet optional:
            $table->string('name')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('item_work_steps', function (Blueprint $table) {
            $table->dropConstrainedForeignId('workflow_id');
            $table->string('name')->nullable(false)->change();
        });
    }
};

