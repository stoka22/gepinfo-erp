<?php
// database/migrations/2025_10_21_000004_adjust_time_entries_for_duration_and_overtime.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // --- Helper: FK dobása, ha létezik (név függetlenül)
        $dropFkIfExists = function (string $table, string $column): void {
            $db = DB::getDatabaseName();
            $row = DB::selectOne(
                "SELECT CONSTRAINT_NAME AS name
                   FROM information_schema.KEY_COLUMN_USAGE
                  WHERE TABLE_SCHEMA = ?
                    AND TABLE_NAME   = ?
                    AND COLUMN_NAME  = ?
                    AND REFERENCED_TABLE_NAME IS NOT NULL
                  LIMIT 1",
                [$db, $table, $column]
            );
            if ($row && isset($row->name)) {
                DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$row->name}`");
            }
        };

        // --- Helper: index létrehozása csak ha hiányzik
        $addIndexIfMissing = function (string $table, string $indexName, array $cols): void {
            $exists = DB::table('information_schema.STATISTICS')
                ->where('TABLE_SCHEMA', DB::getDatabaseName())
                ->where('TABLE_NAME', $table)
                ->where('INDEX_NAME', $indexName)
                ->exists();

            if (! $exists) {
                $colsList = implode('`,`', $cols);
                DB::statement("CREATE INDEX `{$indexName}` ON `{$table}` (`{$colsList}`)");
            }
        };

        // 1) Új oszlop: worked_minutes (összes perc az adott sorban)
        if (!Schema::hasColumn('time_entries', 'worked_minutes')) {
            Schema::table('time_entries', function (Blueprint $t) {
                $t->unsignedInteger('worked_minutes')->nullable()->after('end_time');
            });
        }

        // 2) hours -> DECIMAL(5,2)
        Schema::table('time_entries', function (Blueprint $t) {
            $t->decimal('hours', 5, 2)->nullable()->change();
        });

        // 3) FK-k újralétrehozása nullOnDelete-vel
        foreach (['requested_by', 'approved_by', 'modified_by'] as $col) {
            if (Schema::hasColumn('time_entries', $col)) {
                $dropFkIfExists('time_entries', $col);
            }
        }
        Schema::table('time_entries', function (Blueprint $t) {
            if (Schema::hasColumn('time_entries', 'requested_by')) {
                $t->foreign('requested_by', 'fk_te_requested_by')->references('id')->on('users')->nullOnDelete();
            }
            if (Schema::hasColumn('time_entries', 'approved_by')) {
                $t->foreign('approved_by', 'fk_te_approved_by')->references('id')->on('users')->nullOnDelete();
            }
            if (Schema::hasColumn('time_entries', 'modified_by')) {
                $t->foreign('modified_by', 'fk_te_modified_by')->references('id')->on('users')->nullOnDelete();
            }
        });

        // 4) Hasznos indexek (csak ha hiányoznak)
        $addIndexIfMissing('time_entries', 'te_emp_type_start_idx', ['employee_id','type','start_date']);
        $addIndexIfMissing('time_entries', 'te_type_window_idx',    ['type','start_date','end_date']);
        $addIndexIfMissing('time_entries', 'te_company_date_idx',   ['company_id','start_date']);

        // 5) Konzisztencia: ha hours már ki van töltve, töltsük worked_minutes-t is
        DB::statement("
            UPDATE time_entries
               SET worked_minutes = ROUND(hours * 60)
             WHERE worked_minutes IS NULL AND hours IS NOT NULL
        ");
    }

    public function down(): void
    {
        // --- Helper: index dobása, ha létezik
        $dropIndexIfExists = function (string $table, string $indexName): void {
            $exists = DB::table('information_schema.STATISTICS')
                ->where('TABLE_SCHEMA', DB::getDatabaseName())
                ->where('TABLE_NAME', $table)
                ->where('INDEX_NAME', $indexName)
                ->exists();

            if ($exists) {
                DB::statement("DROP INDEX `{$indexName}` ON `{$table}`");
            }
        };

        // FK-k eltávolítása (névfüggetlen)
        $dropFkIfExists = function (string $table, string $column): void {
            $db = DB::getDatabaseName();
            $row = DB::selectOne(
                "SELECT CONSTRAINT_NAME AS name
                   FROM information_schema.KEY_COLUMN_USAGE
                  WHERE TABLE_SCHEMA = ?
                    AND TABLE_NAME   = ?
                    AND COLUMN_NAME  = ?
                    AND REFERENCED_TABLE_NAME IS NOT NULL
                  LIMIT 1",
                [$db, $table, $column]
            );
            if ($row && isset($row->name)) {
                DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$row->name}`");
            }
        };

        foreach (['requested_by', 'approved_by', 'modified_by'] as $col) {
            if (Schema::hasColumn('time_entries', $col)) {
                $dropFkIfExists('time_entries', $col);
            }
        }

        // Indexek dobása
        $dropIndexIfExists('time_entries', 'te_emp_type_start_idx');
        $dropIndexIfExists('time_entries', 'te_type_window_idx');
        $dropIndexIfExists('time_entries', 'te_company_date_idx');

        // worked_minutes eldobása
        if (Schema::hasColumn('time_entries', 'worked_minutes')) {
            Schema::table('time_entries', function (Blueprint $t) {
                $t->dropColumn('worked_minutes');
            });
        }

        // hours visszaállítása tágabbra (ha rollbackelni akarod a típust is)
        Schema::table('time_entries', function (Blueprint $t) {
            $t->decimal('hours', 10, 2)->nullable()->change();
        });
    }
};
