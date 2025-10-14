<?php
// database/migrations/2025_10_07_000010_add_planning_fields_to_production_splits.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('production_splits', function (Blueprint $t) {
            if (!Schema::hasColumn('production_splits','machine_id'))   $t->foreignId('machine_id')->nullable()->constrained()->nullOnDelete()->after('partner_order_item_id');
            if (!Schema::hasColumn('production_splits','title'))        $t->string('title')->nullable()->after('machine_id');
            if (!Schema::hasColumn('production_splits','start'))        $t->timestamp('start')->nullable()->after('title');
            if (!Schema::hasColumn('production_splits','end'))          $t->timestamp('end')->nullable()->after('start');

            if (!Schema::hasColumn('production_splits','qty_total'))    $t->unsignedInteger('qty_total')->default(0)->after('end');
            if (!Schema::hasColumn('production_splits','qty_from'))     $t->unsignedInteger('qty_from')->default(0)->after('qty_total');
            if (!Schema::hasColumn('production_splits','qty_to'))       $t->unsignedInteger('qty_to')->default(0)->after('qty_from');

            // ⬇️ FIX: nincs unsignedDecimal → decimal-t használunk, és engedjük a nullát
            if (!Schema::hasColumn('production_splits','rate_pph'))     $t->decimal('rate_pph', 10, 3)->nullable()->after('qty_to');

            if (!Schema::hasColumn('production_splits','batch_size'))   $t->unsignedInteger('batch_size')->default(100)->after('rate_pph');
            if (!Schema::hasColumn('production_splits','is_committed')) $t->boolean('is_committed')->default(false)->after('batch_size');

            $t->index(['machine_id','start','end'], 'ps_machine_time_idx');
        });
    }

    public function down(): void
    {
        Schema::table('production_splits', function (Blueprint $t) {
            // index törlése név alapján – ha létezik
            try { $t->dropIndex('ps_machine_time_idx'); } catch (\Throwable $e) {}

            foreach (['is_committed','batch_size','rate_pph','qty_to','qty_from','qty_total','end','start','title','machine_id'] as $col) {
                if (Schema::hasColumn('production_splits',$col)) $t->dropColumn($col);
            }
        });
    }
};
