<?php

namespace App\Console\Commands;

use App\Models\WorkLog;
use App\Models\WorkLogNameAlias;
use Illuminate\Console\Command;

class SeedWorkLogNameAliases extends Command
{
    protected $signature = 'work-logs:seed-name-aliases {--dry : Csak összesítést mutat, nem ír az adatbázisba}';

    protected $description = 'Egyszeri visszamenőleges feltöltés: a work_logs táblában már sikeresen párosított (nev, employee_id) '
        .'párokból megjegyzi az alias-okat, hogy egy utólagos dolgozó-névváltoztatás (pl. becenév hozzáfűzése) ne törje el a '
        .'jövőbeli importok automatikus párosítását ugyanarra a névre. Biztonságosan, ismételten futtatható.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry');

        $pairs = WorkLog::query()
            ->whereNotNull('employee_id')
            ->select('nev', 'employee_id')
            ->distinct()
            ->get();

        // Egy nyers névhez (kis-/nagybetűtől és szélső szóközöktől eltekintve) elvileg
        // mindig ugyanannak a dolgozónak kell tartoznia — ha a történelemben mégis több
        // különböző employee_id fordul elő ugyanahhoz a normalizált névhez, ez félreértést
        // (két dolgozó azonos/hasonló névvel, vagy egy korábbi téves párosítás) jelezhet.
        // Ilyenkor NEM találgatunk: kihagyjuk, és felsoroljuk, hogy kézzel ellenőrizhető legyen.
        $byKey = $pairs->groupBy(fn (WorkLog $w) => mb_strtolower(trim($w->nev)));

        $created = 0;
        $updated = 0;
        $ambiguous = [];

        foreach ($byKey as $key => $group) {
            if ($key === '') {
                continue;
            }

            $employeeIds = $group->pluck('employee_id')->unique();
            if ($employeeIds->count() > 1) {
                $ambiguous[] = $key.' -> employee_id: '.$employeeIds->implode(', ');
                continue;
            }

            $existing = WorkLogNameAlias::where('nev_key', $key)->first();
            if ($existing) {
                if ((int) $existing->employee_id !== (int) $employeeIds->first()) {
                    $ambiguous[] = $key.' -> már létező alias employee_id='.$existing->employee_id.', history employee_id='.$employeeIds->first();
                }
                continue;
            }

            if (! $dry) {
                WorkLogNameAlias::create([
                    'nev'         => $group->first()->nev,
                    'nev_key'     => $key,
                    'employee_id' => $employeeIds->first(),
                ]);
            }
            $created++;
        }

        $this->table(
            ['', 'Darab'],
            [
                ['Egyedi (normalizált) név a work_logs előzményben', $byKey->count()],
                [$dry ? 'Létrehozásra várna (alias)' : 'Most létrehozva (alias)', $created],
                ['Kétértelmű, kihagyva', count($ambiguous)],
            ]
        );

        foreach ($ambiguous as $line) {
            $this->warn($line);
        }

        if ($dry && $created > 0) {
            $this->warn('Ez csak próbafuttatás volt (--dry) — nem történt tényleges mentés. Futtasd újra a --dry kapcsoló nélkül.');
        }

        return self::SUCCESS;
    }
}
