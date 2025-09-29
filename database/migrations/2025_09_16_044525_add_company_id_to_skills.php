<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1) Oszlop felvétele ideiglenesen NULL-lal
        Schema::table('skills', function (Blueprint $table) {
            if (!Schema::hasColumn('skills', 'company_id')) {
                $table->unsignedBigInteger('company_id')->nullable()->index()->after('id');
            }
        });

        // 2) BACKFILL – rugalmas forrás
        DB::transaction(function () {
            // Kiderítjük, honnan tudjuk a cég ID-t
            $hasEmployeeCompany = Schema::hasColumn('employees', 'company_id');
            $hasEmployeeUser    = Schema::hasColumn('employees', 'user_id');
            $hasUserCompany     = Schema::hasColumn('users', 'company_id');

            // a) összeállítjuk a (skill_id -> company_id) párokat
            $pairs = collect();

            if ($hasEmployeeCompany) {
                // employees.company_id elérhető
                $rows = DB::table('employee_skill as es')
                    ->join('employees as e', 'e.id', '=', 'es.employee_id')
                    ->select('es.skill_id', DB::raw('MIN(e.company_id) as company_id'))
                    ->groupBy('es.skill_id')
                    ->get();
                $pairs = collect($rows);
            } elseif ($hasEmployeeUser && $hasUserCompany) {
                // users.company_id elérhető employees.user_id-n keresztül
                $rows = DB::table('employee_skill as es')
                    ->join('employees as e', 'e.id', '=', 'es.employee_id')
                    ->join('users as u', 'u.id', '=', 'e.user_id')
                    ->select('es.skill_id', DB::raw('MIN(u.company_id) as company_id'))
                    ->groupBy('es.skill_id')
                    ->get();
                $pairs = collect($rows);
            }

            // b) beírjuk a skills.company_id-t ott, ahol NULL
            foreach ($pairs as $r) {
                if (!empty($r->company_id)) {
                    DB::table('skills')
                        ->whereNull('company_id')
                        ->where('id', $r->skill_id)
                        ->update(['company_id' => $r->company_id]);
                }
            }

            // c) ha maradt NULL (olyan skill, ami még sehol sem volt használva
            //    VAGY nincs elérhető forrás a céghez), tegyük a legkisebb céghez
            $defaultCompanyId = DB::table('companies')->min('id');
            if ($defaultCompanyId) {
                DB::table('skills')
                    ->whereNull('company_id')
                    ->update(['company_id' => $defaultCompanyId]);
            }
        });

        // 3) Kötelező + FK
        Schema::table('skills', function (Blueprint $table) {
            // ha még mindig NULL-os lenne (elvben nem), most már kötelezővé tesszük
            $table->unsignedBigInteger('company_id')->nullable(false)->change();
        });

        Schema::table('skills', function (Blueprint $table) {
            // tiszta nevű FK
            // ha korábban már felkerült, ez a blokk hibát dobna – ezért try/catch
            try {
                $table->foreign('company_id', 'skills_company_id_foreign')
                    ->references('id')->on('companies')
                    ->cascadeOnDelete();
            } catch (\Throwable $e) {
                // már létezik – ignoráljuk
            }
        });
    }

    public function down(): void
    {
        Schema::table('skills', function (Blueprint $table) {
            if (Schema::hasColumn('skills', 'company_id')) {
                try { $table->dropForeign('skills_company_id_foreign'); } catch (\Throwable $e) {}
                $table->dropColumn('company_id');
            }
        });
    }
};
