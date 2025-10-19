<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// database/migrations/2025_10_15_000001_fix_qty_on_production_splits.php
return new class extends Migration {
    public function up(): void
    {
        // 1) Dobd a meglévő FK-t, ha létezik
        Schema::table('production_splits', function (Blueprint $t) {
            // A hibanévből látjuk a constraint nevét:
            // production_splits_partner_order_item_id_foreign
            if (Schema::hasColumn('production_splits', 'partner_order_item_id')) {
                try { $t->dropForeign('production_splits_partner_order_item_id_foreign'); } catch (\Throwable $e) {}
                try { $t->dropIndex('production_splits_partner_order_item_id_foreign'); } catch (\Throwable $e) {}
            }
        });

        // 2) Típusok módosítása
        Schema::table('production_splits', function (Blueprint $t) {
            if (Schema::hasColumn('production_splits', 'qty')) {
                // qty -> UNSIGNED, DEFAULT 0
                $t->integer('qty')->unsigned()->default(0)->change();
            }
            if (Schema::hasColumn('production_splits', 'partner_order_item_id')) {
                // !!! BIGINT UNSIGNED és NULLABLE, NEM default 0
                $t->unsignedBigInteger('partner_order_item_id')->nullable()->change();
            }
        });

        // 3) 0-k NULL-ra (különben FK visszaállításnál gond lesz)
        DB::table('production_splits')
            ->where('partner_order_item_id', 0)
            ->update(['partner_order_item_id' => null]);

        // 4) FK visszaállítása BIGINT UNSIGNED ↔ BIGINT UNSIGNED-re
        Schema::table('production_splits', function (Blueprint $t) {
            if (Schema::hasColumn('production_splits', 'partner_order_item_id')) {
                $t->foreign('partner_order_item_id', 'ps_poitem_fk')
                  ->references('id')->on('partner_order_items')
                  ->onUpdate('cascade')
                  ->onDelete('set null');  // így törlésnél sem lesz árva 0
            }
        });
    }

    public function down(): void
    {
        // Down: vissza lehet állítani, de általában nem szükséges
        Schema::table('production_splits', function (Blueprint $t) {
            try { $t->dropForeign('ps_poitem_fk'); } catch (\Throwable $e) {}
            // opcionális visszaállítások:
            // $t->integer('qty')->unsigned(false)->default(null)->change();
            // $t->unsignedBigInteger('partner_order_item_id')->nullable(false)->change();
        });
    }
};
