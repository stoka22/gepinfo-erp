<?php

namespace App\Imports;

use App\Enums\TimeEntryStatus;
use App\Enums\TimeEntryType;
use App\Models\Employee;
use App\Models\TimeEntry;
use App\Models\WorkLog;
use App\Support\SpreadsheetEncoding;
use App\Support\TimeRounding;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use DateTime;

class WorkLogsImport
{
    protected array $honapok = [
        'jan.'   => 'Jan.',
        'febr.'  => 'Feb.',
        'márc.'  => 'Mar.',
        'ápr.'   => 'Apr.',
        'máj.'   => 'May.',
        'jún.'   => 'Jun.',
        'júl.'   => 'Jul.',
        'aug.'   => 'Aug.',
        'szept.' => 'Sep.',
        'okt.'   => 'Oct.',
        'nov.'   => 'Nov.',
        'dec.'   => 'Dec.',
    ];

    /**
     * A fájlból kiolvasott, megtisztított sorok (hétvégi/ünnepi/távollét sorok nélkül),
     * még adatbázis-írás nélkül. Ez az alapja mind a dolgozó-párosítás
     * felderítésének, mind a tényleges importnak.
     *
     * @return array<int, array{nev:string, munkakor:?string, helyiseg:?string, belepesi_pont:?string, kezdes:?string, kilepesi_pont:?string, vege:?string, ido:?string}>
     */
    public function parseRows(string $filePath): array
    {
        $spreadsheet = SpreadsheetEncoding::loadNormalized($filePath);
        $sheet = $spreadsheet->getActiveSheet();

        $rows = [];

        // Ha a lapon nincs a fejlécen (1. sor) kívül semmi (pl. valaki a "keret" .xls
        // fájlt tölti fel az Excel "mentés weblapként" exportnál a tényleges adatokat
        // tartalmazó .htm helyett), a RowIterator(2) hívás kivételt dobna — ilyenkor
        // egyszerűen nincs mit importálni.
        if ($sheet->getHighestRow() < 2) {
            return $rows;
        }

        // A HTML-alapú "fake xls" exportoknál soronként eltérhet a cellák száma (pl. egy
        // üres/rövidebb sor), ezért a sor saját legmagasabb oszlopa helyett a TELJES lap
        // legmagasabb oszlopáig kényszerítjük az iterálást, hogy minden sor egyenlő
        // hosszúságú (üresekkel kitöltött) cellalistát adjon.
        $highestColumn = $sheet->getHighestColumn();

        foreach ($sheet->getRowIterator(2) as $row) {
            $cellIterator = $row->getCellIterator('A', $highestColumn);
            $cellIterator->setIterateOnlyExistingCells(false);

            $cells = [];
            foreach ($cellIterator as $cell) {
                $cells[] = $cell;
            }

            $value = fn (int $i): ?string => isset($cells[$i])
                ? SpreadsheetEncoding::fixMojibake(trim((string) $cells[$i]->getValue()))
                : null;

            if (empty($value(0))) {
                continue;
            }

            $kezdes = isset($cells[4]) ? $this->parseDate($cells[4], 'kezdes', $row->getRowIndex()) : null;
            $vege = isset($cells[6]) ? $this->parseDate($cells[6], 'vege', $row->getRowIndex()) : null;

            // Nincs sem belépés, sem kilépés időpont rögzítve (hétvége/ünnep/távollét sor) –
            // ez nem tényleges munkanapló-bejegyzés, nem importáljuk.
            if ($kezdes === null && $vege === null) {
                continue;
            }

            $rows[] = [
                'nev'           => $value(0),
                'munkakor'      => $value(1),
                'helyiseg'      => $value(2),
                'belepesi_pont' => $value(3),
                'kezdes'        => $kezdes,
                'kilepesi_pont' => $value(5),
                'vege'          => $vege,
                'ido'           => self::formatIdo($value(7)),
            ];
        }

        return $rows;
    }

    /**
     * A fájlban szereplő nevek közül azok, amikhez (kis-/nagybetűtől függetlenül,
     * pontos egyezéssel) NEM található dolgozó – ezekhez kézzel kell választani
     * importáláskor, hogy ne maradjanak dolgozó nélkül.
     *
     * @return array<int, string>
     */
    public function unmatchedNames(string $filePath): array
    {
        return $this->unmatchedNamesFromRows($this->parseRows($filePath));
    }

    /**
     * Ugyanaz, mint az unmatchedNames(), de már beolvasott sorokból – hogy nagy
     * fájloknál ne kelljen többször újraolvasni/feldolgozni ugyanazt a fájlt.
     *
     * @param  array<int, array{nev:string}>  $rows
     * @return array<int, string>
     */
    public function unmatchedNamesFromRows(array $rows): array
    {
        $employeeIdsByName = $this->employeeIdsByName();

        return collect($rows)
            ->pluck('nev')
            ->unique()
            ->reject(fn (string $nev) => $employeeIdsByName->has(mb_strtolower(trim($nev))))
            ->sort()
            ->values()
            ->all();
    }

    /**
     * A sorok névvel kiegészítve, feloldott (vagy fel nem oldott) dolgozó-azonosítóval –
     * ez adja az importálás előtti ellenőrző előnézet alapját is, hogy a problémás
     * (dolgozó nélkül maradó) sorok megjelenítés előtt kiderüljenek.
     *
     * @param  array<string, int|null>  $employeeAssignments
     * @return array<int, array{nev:string, munkakor:?string, helyiseg:?string, belepesi_pont:?string, kezdes:?string, kilepesi_pont:?string, vege:?string, ido:?string, employee_id:?int}>
     */
    public function resolveRows(string $filePath, array $employeeAssignments = []): array
    {
        return $this->resolveParsedRows($this->parseRows($filePath), $employeeAssignments);
    }

    /**
     * Ugyanaz, mint a resolveRows(), de már beolvasott sorokból.
     *
     * @param  array<int, array{nev:string}>  $rows
     * @param  array<string, int|null>  $employeeAssignments
     * @return array<int, array{nev:string, munkakor:?string, helyiseg:?string, belepesi_pont:?string, kezdes:?string, kilepesi_pont:?string, vege:?string, ido:?string, employee_id:?int}>
     */
    public function resolveParsedRows(array $rows, array $employeeAssignments = []): array
    {
        $employeeIdsByName = $this->employeeIdsByName();

        return array_map(
            fn (array $row) => $row + [
                'employee_id' => $employeeIdsByName->get(mb_strtolower(trim($row['nev'])))
                    ?? $employeeAssignments[$row['nev']]
                    ?? null,
            ],
            $rows
        );
    }

    /**
     * Importálja a fájlt: minden sort a névhez automatikusan párosított dolgozóhoz
     * rendel (kis-/nagybetűtől független pontos névegyezés). Azokra a nevekre,
     * amikhez nincs automatikus egyezés, a $employeeAssignments-ben megadott
     * kézi hozzárendelést használja (nev => employee_id); ha egy névhez ott sincs
     * megadva semmi, a sor dolgozó nélkül kerül be (a listán később kézzel is
     * hozzárendelhető).
     *
     * @param  array<string, int|null>  $employeeAssignments
     */
    public function import(string $filePath, array $employeeAssignments = []): int
    {
        return $this->importResolvedRows($this->resolveRows($filePath, $employeeAssignments));
    }

    /**
     * Ugyanaz, mint az import(), de már feloldott (employee_id-vel kiegészített) sorokból –
     * hogy nagy fájloknál a beolvasás/egyeztetés/mentés lépések ne olvassák be
     * többször ugyanazt a (akár több tíz MB-os) fájlt.
     *
     * @param  array<int, array{nev:string, munkakor:?string, helyiseg:?string, belepesi_pont:?string, kezdes:?string, kilepesi_pont:?string, vege:?string, ido:?string, employee_id:?int}>  $resolvedRows
     */
    public function importResolvedRows(array $resolvedRows): int
    {
        $count = 0;

        foreach ($resolvedRows as $row) {
            WorkLog::create($row);
            $count++;

            // A work_logs tábla önmagában csak egy nyers napló – a jelenléti ívet és a
            // túlóra-keretet kizárólag a time_entries tábla táplálja, ezért minden
            // dolgozóhoz sikeresen párosított sorból egy megfelelő "presence" bejegyzést
            // is létre kell hozni, különben az importált adat sosem jelenne meg az íven.
            if (! empty($row['employee_id'])) {
                $this->createPresenceEntry($row);
            }
        }

        return $count;
    }

    /**
     * A resolveRows()/import() által előállított sorból létrehozza a megfelelő
     * time_entries (presence) bejegyzést – ugyanazzal a fél órára felfelé kerekítéssel,
     * mint amit a napi bontású import (ImportDailyAttendance) is használ, hogy a két
     * forrásból számolt túlóra/jelenlét összemérhető legyen.
     *
     * @param  array{kezdes:?string, vege:?string, helyiseg:?string, employee_id:?int}  $row
     */
    protected function createPresenceEntry(array $row): void
    {
        $kezdes = $row['kezdes'] ? CarbonImmutable::parse($row['kezdes']) : null;
        $vege   = $row['vege'] ? CarbonImmutable::parse($row['vege']) : null;

        $startDate = $kezdes?->toDateString() ?? $vege?->toDateString();
        $startTime = $kezdes ? TimeRounding::roundStartUpToHalfHour($kezdes->format('H:i')).':00' : null;
        $endTime   = $vege?->format('H:i:s');

        // Duplikáció-védelem: ha ugyanazt a fájlt (vagy átfedő időszakot) véletlenül
        // kétszer importálják, ne kerüljön be kétszer ugyanaz a szakasz.
        $exists = TimeEntry::query()
            ->where('employee_id', $row['employee_id'])
            ->where('entry_method', 'worklog-import')
            ->where('start_date', $startDate)
            ->where('start_time', $startTime)
            ->where('end_time', $endTime)
            ->exists();

        if ($exists) {
            return;
        }

        TimeEntry::create([
            'employee_id'    => $row['employee_id'],
            'type'           => TimeEntryType::Presence->value,
            'status'         => $endTime ? TimeEntryStatus::CheckedOut->value : TimeEntryStatus::CheckedIn->value,
            'start_date'     => $startDate,
            'start_time'     => $startTime,
            'raw_start_time' => $kezdes ? $kezdes->format('H:i:s') : null,
            'end_date'       => $endTime ? ($vege?->toDateString()) : null,
            'end_time'       => $endTime,
            'entry_method'   => 'worklog-import',
            // Pontosan az egyik időpont hiányzik (nem hétvégi/ünnepi/távollét sor, azt a
            // parseRows() már kiszűrte) – ez hiányos rögzítés, felülvizsgálatra vár.
            'needs_review'   => is_null($kezdes) !== is_null($vege),
            'location'       => $row['helyiseg'] ?? null,
        ]);
    }

    /**
     * Az "Idő" cella órák:percek formátumra hozása. Az Excel export egyes celláit
     * nap-törtrészként (pl. "0.16458333333333" = kb. 3 óra 57 perc) tárolja, mást már
     * eleve "H:MM" szöveges formátumban — mindkettőt egységes "H:MM" alakra hozzuk.
     * Ugyanezt a logikát a megjelenítés (WorkLogResource tábla) is újrahasznosítja a
     * korábban, ennek a javításnak az elkészülte előtt importált, nyers tört-alakú
     * sorok helyes kijelzéséhez.
     */
    public static function formatIdo(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (! is_numeric($value)) {
            return $value;
        }

        $totalMinutes = (int) round(((float) $value) * 24 * 60);
        $hours = intdiv($totalMinutes, 60);
        $minutes = $totalMinutes % 60;

        return sprintf('%d:%02d', $hours, $minutes);
    }

    /** Dolgozó-azonosítók név szerint (kisbetűsítve, trimmelve), a pontos-egyezés kereséséhez. */
    protected function employeeIdsByName(): \Illuminate\Support\Collection
    {
        return Employee::pluck('id', 'name')
            ->mapWithKeys(fn ($id, $name) => [mb_strtolower(trim((string) $name)) => $id]);
    }

    protected function parseDate($cell, string $field, int $rowIndex): ?string
    {
        $value = trim((string)$cell->getValue());
        if ($value === '') {
            return null;
        }

        // Ha Excel dátum típusú
        if (\PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime($cell)) {
            return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($cell->getValue())->format('Y-m-d H:i:s');
        }

        // Magyar hónapnév csere
        $converted = str_replace(array_keys($this->honapok), array_values($this->honapok), $value);

        // Tisztítás: pontok és szóközök normalizálása
        $converted = preg_replace('/\s+/', ' ', $converted);
        $converted = preg_replace('/\.+/', '.', $converted);

        // Próbáljuk explicit formátummal
        $dateTime = \DateTime::createFromFormat('Y. M. j. H:i:s', $converted);

        if ($dateTime) {
            return $dateTime->format('Y-m-d H:i:s');
        }

        // Fallback: strtotime()
        $timestamp = strtotime($converted);
        if ($timestamp) {
            return date('Y-m-d H:i:s', $timestamp);
        }

        // Ha semmi nem sikerült, logoljuk
        Log::warning("Nem sikerült konvertálni a dátumot:'{$converted}'- '{$value}' (mező: {$field}, sor: {$rowIndex})");
        return null;
    }
}
