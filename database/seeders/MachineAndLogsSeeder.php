<?php

namespace Database\Seeders;

use App\Models\Machine;
use App\Models\ProductionLog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;


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

            // --- kis segédfüggvény: egyedi kód előállítása
            $makeUniqueCode = function (string $base) {
                $codeBase = Str::upper(Str::slug($base, '_'));     // pl. "300KN_1_DAR_BP"
                $code     = $codeBase;
                $i = 2;
                while (Machine::where('code', $code)->exists()) {
                    $code = $codeBase.'_'.$i;
                    $i++;
                }
                return $code;
            };

            // Gépek felvétele (code kötelező!)
            $machines = collect($names)->map(function ($name) use ($makeUniqueCode) {
                $code = $makeUniqueCode($name);

                // code alapján keressünk/hozzunk létre
                return Machine::firstOrCreate(
                    ['code' => $code],
                    [
                        'name'         => $name,
                        'active'       => true,     // ha kötelező nálad
                        'location'     => null,
                        'vendor'       => null,
                        'model'        => null,
                        'serial'       => null,
                        'commissioned_at' => null,
                        'notes'        => null,
                        'cron_enabled' => false,    // kézzel kapcsolod majd
                    ]
                );
            });

            // Elmúlt 24 óra perces logok
            $end   = now()->startOfMinute();
            $start = $end->clone()->subHours(24);

            foreach ($machines as $machine) {
                $rows = [];
                $t = $start->clone();

                while ($t <= $end) {
                    $rows[] = [
                        'machine_id' => $machine->id,
                        'qty'        => random_int(0, 5),
                        'created_at' => $t->toDateTimeString(),
                    ];
                    $t->addMinute();
                }


                foreach (array_chunk($rows, 1000) as $chunk) {
                    DB::table('production_logs')->insert($chunk);
                }
            }
        });
    }
}
