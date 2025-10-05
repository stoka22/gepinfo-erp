<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class MachineAndLogsSeeder extends Seeder
{
    public function run(): void
    {
        $names = [
            '300KN 1 dar. BP',
            'Automata I. szegecs',
            'Automata I. BP hajl.',
            '300kN II. dar. KP',
            'Automata II. KP hajl.',
            'Toxoló KP Belső fül',
            'Toxoló KP Külső fül',
            '250KN II. dar. fülek',
            '250kN dar. bilincs',
            'Bil. prés I.-1600 kN',
            'Hidr. M10',
            'Hidr. M6',
            'OK szegecs',
            'OK bilincs szegecs.',
            'OK szártekerő',
            'test',
            'test 2',
        ];

        DB::transaction(function () use ($names) {

            // --- 0) company_id felderítés (ha van ilyen oszlop a machines táblában)
            $hasCompanyOnMachines = Schema::hasColumn('machines', 'company_id');
            $hasCompanyOnLogs     = Schema::hasColumn('production_logs', 'company_id');

            $companyId = null;
            if ($hasCompanyOnMachines) {
                // ❶ Próbáljuk a legkisebb (első) company id-t venni
                $companyId = DB::table('companies')->min('id');

                // ❷ Ha nincs cég, de az .env-ben megadtál egyet, használd azt
                if (!$companyId && env('SEED_COMPANY_ID')) {
                    $companyId = (int) env('SEED_COMPANY_ID');
                }

                // ❸ Ha továbbra sincs, álljunk le érthető hibával
                if (!$companyId) {
                    throw new \RuntimeException(
                        "Nincs elérhető company. Hozz létre legalább egy rekordot a 'companies' táblában, vagy állítsd be a SEED_COMPANY_ID-t az .env-ben."
                    );
                }
            }

            // --- 1) Gépek felvétele / frissítése (code + company_id szerint)
            $now = now();

            foreach ($names as $name) {
                $codeBase = Str::upper(Str::slug($name, '_')); // pl. "300KN_1_DAR_BP"

                // A code-ot cégen belül tekintjük egyedinek
                $uniqueWhere = $hasCompanyOnMachines
                    ? ['code' => $codeBase, 'company_id' => $companyId]
                    : ['code' => $codeBase];

                $updateData = [
                    'name'            => $name,
                    'active'          => 1,
                    'location'        => null,
                    'vendor'          => null,
                    'model'           => null,
                    'serial'          => null,
                    'commissioned_at' => null,
                    'notes'           => null,
                    'cron_enabled'    => 0,
                    'updated_at'      => $now,
                ];

                if ($hasCompanyOnMachines) {
                    $updateData['company_id'] = $companyId;
                }

                // updateOrInsert nem tölti automatikusan a created_at-et insertnél, ezért külön adjuk
                DB::table('machines')->updateOrInsert(
                    $uniqueWhere,
                    $updateData + ['created_at' => $now]
                );
            }

            // --- 2) Perces logok (elmúlt 24 óra)
            $end   = now()->startOfMinute();
            $start = $end->clone()->subHours(24);

            // gépek lekérése a friss állapottal
            $machinesQuery = DB::table('machines')->select('id', 'code');
            if ($hasCompanyOnMachines) {
                $machinesQuery->where('company_id', $companyId);
            }
            $machines = $machinesQuery->get();

            foreach ($machines as $m) {
                $rows = [];
                $t = $start->clone();

                while ($t <= $end) {
                    $row = [
                        'machine_id' => $m->id,
                        'qty'        => random_int(0, 5),
                        'created_at' => $t->toDateTimeString(),
                        'updated_at' => $t->toDateTimeString(),
                    ];
                    if ($hasCompanyOnLogs) {
                        $row['company_id'] = $companyId;
                    }
                    $rows[] = $row;
                    $t->addMinute();
                }

                foreach (array_chunk($rows, 1000) as $chunk) {
                    DB::table('production_logs')->insert($chunk);
                }
            }
        });
    }
}
