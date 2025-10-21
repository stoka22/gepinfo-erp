<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private function getFkName(string $table, string $column): ?string
    {
        $db = DB::getDatabaseName();
        $row = DB::selectOne(
            "SELECT CONSTRAINT_NAME
               FROM information_schema.KEY_COLUMN_USAGE
              WHERE TABLE_SCHEMA = ?
                AND TABLE_NAME   = ?
                AND COLUMN_NAME  = ?
                AND REFERENCED_TABLE_NAME IS NOT NULL
              LIMIT 1",
            [$db, $table, $column]
        );
        return $row->CONSTRAINT_NAME ?? null;
    }

    private function indexExists(string $table, string $index): bool
    {
        $db  = DB::getDatabaseName();
        $row = DB::selectOne(
            "SELECT INDEX_NAME
               FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA = ?
                AND TABLE_NAME   = ?
                AND INDEX_NAME   = ?
              LIMIT 1",
            [$db, $table, $index]
        );
        return (bool)($row->INDEX_NAME ?? null);
    }

    public function up(): void
    {
        $table  = 'production_splits';
        $column = 'partner_order_item_id';

        // 1) Külső kulcs ledobása, ha van
        if ($fk = $this->getFkName($table, $column)) {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$fk}`");
        }

        // 2) Típusok módosítása
        Schema::table($table, function (Blueprint $t) use ($column) {
            if (Schema::hasColumn($t->getTable(), 'qty')) {
                $t->integer('qty')->unsigned()->default(0)->change();
            }
            if (Schema::hasColumn($t->getTable(), $column)) {
                // FK-hoz: BIGINT UNSIGNED, inkább NULLABLE (ne default 0)
                $t->unsignedBigInteger($column)->nullable()->change();
            }
        });

        // 3) 0 -> NULL (különben FK visszaállításnál gond)
        DB::table($table)->where($column, 0)->update([$column => null]);

        // (opcionális) ha volt olyan index, ami a régi FK nevével egyezett, és még létezik:
        // if ($this->indexExists($table, 'production_splits_partner_order_item_id_foreign')) {
        //     DB::statement("ALTER TABLE `{$table}` DROP INDEX `production_splits_partner_order_item_id_foreign`");
        // }

        // 4) FK visszaállítása helyes típusokkal
        Schema::table($table, function (Blueprint $t) use ($column) {
            if (Schema::hasColumn($t->getTable(), $column)) {
                $t->foreign($column, 'ps_poitem_fk')
                  ->references('id')
                  ->on('partner_order_items')
                  ->onUpdate('cascade')
                  ->onDelete('set null'); // jobb, mint default 0
            }
        });
    }

    public function down(): void
    {
        // Visszaalakítás csak ha tényleg szükséges
        Schema::table('production_splits', function (Blueprint $t) {
            // dobd az általunk létrehozott FK-t
            try { $t->dropForeign('ps_poitem_fk'); } catch (\Throwable $e) {}
            // opcionális: itt állítsd vissza a típusokat
        });
    }
};
