<?php
// database/migrations/2025_09_16_150000_add_company_id_to_time_entries.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1) Új oszlop ideiglenesen NULL-lal
        Schema::table('time_entries', function (Blueprint $table) {
            if (!Schema::hasColumn('time_entries', 'company_id')) {
                $table->unsignedBigInteger('company_id')->nullable()->after('id')->index();
            }
        });

        // 2) BACKFILL – több forrásból, robusztusan
        DB::transaction(function () {
            $hasEmployeeCompany = Schema::hasColumn('employees', 'company_id');
            $hasEmployeeUser    = Schema::hasColumn('employees', 'user_id');
            $hasUserCompany     = Schema::hasColumn('users', 'company_id');

            // a) ha employees.company_id elérhető, onnan
            if ($hasEmployeeCompany) {
                DB::statement("
                    UPDATE time_entries te
                    JOIN employees e ON e.id = te.employee_id
                    SET te.company_id = e.company_id
                    WHERE te.company_id IS NULL AND e.company_id IS NOT NULL
                ");
            }

            // b) ha nincs employees.company_id, de van employees.user_id + users.company_id
            if (!$hasEmployeeCompany && $hasEmployeeUser && $hasUserCompany) {
                DB::statement("
                    UPDATE time_entries te
                    JOIN employees e ON e.id = te.employee_id
                    JOIN users u ON u.id = e.user_id
                    SET te.company_id = u.company_id
                    WHERE te.company_id IS NULL AND u.company_id IS NOT NULL
                ");
            }

            // c) requester (requested_by) alapján
            if ($hasUserCompany) {
                DB::statement("
                    UPDATE time_entries te
                    JOIN users u ON u.id = te.requested_by
                    SET te.company_id = u.company_id
                    WHERE te.company_id IS NULL AND te.requested_by IS NOT NULL AND u.company_id IS NOT NULL
                ");
            }

            // d) approver (approved_by) alapján
            if ($hasUserCompany) {
                DB::statement("
                    UPDATE time_entries te
                    JOIN users u ON u.id = te.approved_by
                    SET te.company_id = u.company_id
                    WHERE te.company_id IS NULL AND te.approved_by IS NOT NULL AND u.company_id IS NOT NULL
                ");
            }

            // e) fallback: legkisebb companies.id
            $defaultCompanyId = DB::table('companies')->min('id');
            if ($defaultCompanyId) {
                DB::table('time_entries')
                    ->whereNull('company_id')
                    ->update(['company_id' => $defaultCompanyId]);
            }
        });

        // 3) Kötelező + FK
        Schema::table('time_entries', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable(false)->change();
        });

        Schema::table('time_entries', function (Blueprint $table) {
            try {
                $table->foreign('company_id', 'time_entries_company_id_foreign')
                    ->references('id')->on('companies')
                    ->cascadeOnDelete();
            } catch (\Throwable $e) {
                // ha már létezik, csendben tovább
            }
        });

        // (Opcionális) cégen belüli üzleti egyediség (pl. ugyanarra az employee+időszakra ne lehessen duplikálni)
        // Schema::table('time_entries', function (Blueprint $table) {
        //     $table->unique(['company_id','employee_id','type','start_date','end_date'], 'time_entries_company_emp_type_range_unique');
        // });
    }

    public function down(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            // try/catch, ha nem léteznek az indexek
            try { $table->dropForeign('time_entries_company_id_foreign'); } catch (\Throwable $e) {}
            // try { $table->dropUnique('time_entries_company_emp_type_range_unique'); } catch (\Throwable $e) {}
            if (Schema::hasColumn('time_entries', 'company_id')) {
                $table->dropColumn('company_id');
            }
        });
    }
};
