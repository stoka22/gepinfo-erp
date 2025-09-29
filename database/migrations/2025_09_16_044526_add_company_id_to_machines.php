<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1) Oszlop felvétele ideiglenesen NULL-lal
        Schema::table('machines', function (Blueprint $table) {
            if (!Schema::hasColumn('machines', 'company_id')) {
                $table->unsignedBigInteger('company_id')->nullable()->after('id')->index();
            }
        });

        // 2) Backfill – ha a devices táblában van company_id, vegyük át onnan
        DB::transaction(function () {
            if (Schema::hasColumn('devices', 'company_id') && Schema::hasColumn('devices', 'machine_id')) {
                // ahol a géphez tartozó eszköz(ök) céghez vannak kötve, vegyük a legkisebb company_id-t
                DB::statement("
                    UPDATE machines m
                    JOIN (
                        SELECT d.machine_id, MIN(d.company_id) AS company_id
                        FROM devices d
                        WHERE d.company_id IS NOT NULL
                        GROUP BY d.machine_id
                    ) x ON x.machine_id = m.id
                    SET m.company_id = x.company_id
                    WHERE m.company_id IS NULL
                ");
            }

            // fallback: tegyük a legkisebb companies.id-hez, ha maradt NULL
            $defaultCompanyId = DB::table('companies')->min('id');
            if ($defaultCompanyId) {
                DB::table('machines')->whereNull('company_id')
                    ->update(['company_id' => $defaultCompanyId]);
            }
        });

        // 3) Kötelező + FK + (opcionális) egyediség kódra cégen belül
        Schema::table('machines', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable(false)->change();
        });

        Schema::table('machines', function (Blueprint $table) {
            try {
                $table->foreign('company_id', 'machines_company_id_foreign')
                    ->references('id')->on('companies')
                    ->cascadeOnDelete();
            } catch (\Throwable $e) {}

            // ha a "code" mező létezik: egyediség cégen belül
            if (Schema::hasColumn('machines', 'code')) {
                try {
                    $table->unique(['company_id', 'code'], 'machines_company_code_unique');
                } catch (\Throwable $e) {}
            }
        });
    }

    public function down(): void
    {
        Schema::table('machines', function (Blueprint $table) {
            // indexek eldobása
            try { $table->dropUnique('machines_company_code_unique'); } catch (\Throwable $e) {}
            try { $table->dropForeign('machines_company_id_foreign'); } catch (\Throwable $e) {}
            if (Schema::hasColumn('machines', 'company_id')) {
                $table->dropColumn('company_id');
            }
        });
    }
};
