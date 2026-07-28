<?php

namespace App\Console\Commands;

use App\Imports\WorkLogsImport;
use Illuminate\Console\Command;

class ImportWorkLogsXls extends Command
{
    protected $signature = 'work-logs:import
        {file : A munkanapló (belépő/kilépő) export elérési útja (.xls/.htm)}
        {--dry : Csak összesítés, nincs írás}';

    protected $description = 'Munkanapló export importja a work_logs táblába, SSH-ról — nagy, több dolgozós fájlokhoz, '
        .'amik a webes felület feltöltési/memória korlátai miatt ott nem importálhatók. A névvel nem azonosítható '
        .'sorok dolgozó nélkül kerülnek be; ezeket utólag a Munkaidő napló listában, az "Összekapcsolás dolgozóval" '
        .'tömeges művelettel lehet hozzárendelni (vagy előbb létrehozni a hiányzó dolgozói adatlapot).';

    public function handle(): int
    {
        $file = $this->argument('file');
        $dry = (bool) $this->option('dry');

        if (! is_file($file)) {
            $this->error("Nincs fájl: {$file}");
            return self::FAILURE;
        }

        // Nagy (több tízezer soros) export a CLI alapértelmezett (gyakran 128M-es)
        // memóriakeretét is meghaladhatja már a beolvasás közben; ez a parancs mindig
        // önmagának emeli meg, hogy ne kelljen külön -d memory_limit=... kapcsolót
        // megjegyezni a futtatáshoz.
        ini_set('memory_limit', '1024M');

        $import = new WorkLogsImport;

        // A (potenciálisan nagy, akár több tíz MB-os) fájlt EGYSZER olvassuk be, és az
        // összesítéshez, az egyeztetéshez, majd a mentéshez is ugyanazt a már beolvasott
        // sor-tömböt használjuk újra — nem olvassuk/dolgozzuk fel többször ugyanazt a fájlt.
        $this->info('Fájl beolvasása...');
        $rows = $import->parseRows($file);
        $this->info('Beolvasva: '.count($rows).' sor.');

        $distinctNames = collect($rows)->pluck('nev')->unique();
        $unmatched = $import->unmatchedNamesFromRows($rows);

        $this->line('');
        $this->line('Egyedi nevek a fájlban: '.$distinctNames->count());
        $this->line('Nem azonosítható nevek: '.count($unmatched));
        foreach ($unmatched as $nev) {
            $this->line("  - {$nev}");
        }
        $this->line('');

        if ($dry) {
            $this->warn('Dry-run, nincs írás.');
            return self::SUCCESS;
        }

        if (! $this->confirm(count($rows).' sor importálása a work_logs táblába. Folytatod?', true)) {
            $this->warn('Megszakítva.');
            return self::SUCCESS;
        }

        $resolvedRows = $import->resolveParsedRows($rows);
        $count = $import->importResolvedRows($resolvedRows);

        $this->info("Kész: {$count} sor importálva.");

        if (count($unmatched)) {
            $this->warn(
                count($unmatched).' név nem lett automatikusan dolgozóhoz rendelve. '
                .'A Munkaidő napló listában, az "Összekapcsolás dolgozóval" tömeges művelettel rendelheted hozzá '
                .'őket (ha valaki még nem szerepel a Dolgozók között, előbb ott hozd létre az adatlapját).'
            );
        }

        return self::SUCCESS;
    }
}
