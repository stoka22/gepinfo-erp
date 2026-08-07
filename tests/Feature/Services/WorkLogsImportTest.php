<?php

use App\Imports\WorkLogsImport;
use App\Models\Company;
use App\Models\Employee;
use App\Models\WorkLog;

function worklogFakeXls(string $html): string
{
    $path = tempnam(sys_get_temp_dir(), 'worklog_test_') . '.xls';
    file_put_contents($path, $html);

    return $path;
}

it('returns no rows (instead of crashing) for a sheet with only a header row', function () {
    // Az Excel "mentés weblapként" export egy több fájlból álló csomagot hoz létre: a
    // fő .xls csak egy "keret" (frameset) 1 sornyi tartalommal, a valódi adat egy
    // különálló .htm fájlban van. Ha valaki véletlenül a keret-fájlt tölti fel, a
    // RowIterator(2) hívás korábban "Start row (2) is beyond highest row (1)" hibával
    // elhasalt ahelyett, hogy egyszerűen jelezné: nincs importálható sor.
    $html = '<html><body><table><tr><td>Csak fejléc</td></tr></table></body></html>';
    $path = worklogFakeXls($html);

    $import = new WorkLogsImport;
    expect($import->parseRows($path))->toBe([]);
    expect($import->unmatchedNames($path))->toBe([]);

    unlink($path);
});

it('imports a row with fewer cells than the header without crashing (short row)', function () {
    // Ez pontosan az éles "Undefined array key 6" hibát reprodukálja: a HTML-alapú
    // "fake xls" exportnál egy sornak kevesebb <td> cellája van, mint a teljes
    // fejlécnek (pl. hiányzó kilépési pont/vég/idő).
    $html = '<html><body><table>'
        . '<tr><td>Nev</td><td>Munkakor</td><td>Helyiseg</td><td>Belepesi</td><td>Kezdes</td><td>Kilepesi</td><td>Vege</td><td>Ido</td></tr>'
        . '<tr><td>Rövid Sor Teszt</td><td>Operátor</td><td>Üzem</td><td>Kapu 1</td><td>2026. jan. 5. 08:00:00</td></tr>'
        . '</table></body></html>';
    $path = worklogFakeXls($html);

    (new WorkLogsImport)->import($path);

    $row = WorkLog::where('nev', 'Rövid Sor Teszt')->first();
    expect($row)->not->toBeNull();
    expect($row->munkakor)->toBe('Operátor');
    expect($row->belepesi_pont)->toBe('Kapu 1');
    expect($row->kilepesi_pont)->toBe('');
    expect($row->vege)->toBeNull();

    unlink($path);
});

it('skips a row that has a name but no start/end time (weekend/holiday/absence row)', function () {
    // Az éles fájlban a hétvégi/ünnepi/távollét napokhoz is van egy sor a dolgozó nevével,
    // de tényleges be-/kilépési időpont nélkül – ezeket NEM szabad munkanapló-bejegyzésként
    // importálni.
    $html = '<html><body><table>'
        . '<tr><td>Nev</td><td>Munkakor</td><td>Helyiseg</td><td>Belepesi</td><td>Kezdes</td><td>Kilepesi</td><td>Vege</td><td>Ido</td></tr>'
        . '<tr><td>Hetvegi Sor Teszt</td><td>Operátor</td><td>Üzem</td><td></td><td></td><td></td><td></td><td></td></tr>'
        . '</table></body></html>';
    $path = worklogFakeXls($html);

    (new WorkLogsImport)->import($path);

    expect(WorkLog::where('nev', 'Hetvegi Sor Teszt')->exists())->toBeFalse();

    unlink($path);
});

it('imports a full row with all columns correctly', function () {
    $html = '<html><body><table>'
        . '<tr><td>Nev</td><td>Munkakor</td><td>Helyiseg</td><td>Belepesi</td><td>Kezdes</td><td>Kilepesi</td><td>Vege</td><td>Ido</td></tr>'
        . '<tr><td>Teljes Sor Teszt</td><td>Vezető</td><td>Iroda</td><td>Kapu 2</td><td>2026. jan. 5. 08:05:00</td><td>Kapu 2</td><td>2026. jan. 5. 16:30:00</td><td>08:25</td></tr>'
        . '</table></body></html>';
    $path = worklogFakeXls($html);

    (new WorkLogsImport)->import($path);

    $row = WorkLog::where('nev', 'Teljes Sor Teszt')->first();
    expect($row)->not->toBeNull();
    expect($row->kilepesi_pont)->toBe('Kapu 2');
    expect($row->ido)->toBe('08:25');

    unlink($path);
});

it('does not create a duplicate presence entry when the shift was already imported by a different source (daily-import)', function () {
    // Éles hiba: a napi bontású import (ImportDailyAttendance, entry_method=daily-import)
    // és a munkanapló-szinkron (WorkLogsImport, entry_method=worklog-import) egymástól
    // függetlenül importálhatja ugyanazt a műszakot — a duplikáció-védelem korábban csak
    // a SAJÁT forrása ellen nézett, ezért a második import mindig létrehozott egy második,
    // majdnem azonos sort, megduplázva a ledolgozott időt/túlórát a jelenléti íven.
    $company = Company::create(['name' => 'Dedup Kft.']);
    $employee = Employee::create(['name' => 'Dedup Teszt', 'company_id' => $company->id]);

    // Egy korábbi daily-import már felvitte ezt a napot; a daily-import mindig :00
    // másodperccel ír, a worklog-import forrás viszont a valós másodpercet őrzi meg (16:30:07)
    // — a duplikáció-védelemnek ezt is fel kell ismernie, nem csak a pontos egyezést.
    \App\Models\TimeEntry::create([
        'employee_id'  => $employee->id,
        'company_id'   => $company->id,
        'type'         => 'presence',
        'status'       => 'checked_out',
        'start_date'   => '2026-01-05',
        'start_time'   => '08:00:00',
        'end_date'     => '2026-01-05',
        'end_time'     => '16:30:00',
        'entry_method' => 'daily-import',
    ]);

    $html = '<html><body><table>'
        . '<tr><td>Nev</td><td>Munkakor</td><td>Helyiseg</td><td>Belepesi</td><td>Kezdes</td><td>Kilepesi</td><td>Vege</td><td>Ido</td></tr>'
        . '<tr><td>Dedup Teszt</td><td>Op</td><td>Uzem</td><td>K1</td><td>2026. jan. 5. 08:00:00</td><td>K1</td><td>2026. jan. 5. 16:30:07</td><td>08:30</td></tr>'
        . '</table></body></html>';
    $path = worklogFakeXls($html);

    (new WorkLogsImport)->import($path);

    expect(\App\Models\TimeEntry::where('employee_id', $employee->id)->where('type', 'presence')->count())->toBe(1);

    unlink($path);
});

it('converts a raw Excel day-fraction "ido" value to H:MM on import', function () {
    // Az Excel export egyes celláit nap-törtrészként adja vissza (pl. "0.16458333333333"
    // ~ 3 óra 57 perc), nem pedig "3:57" szöveges alakban — ezt kell H:MM formára hozni.
    $html = '<html><body><table>'
        . '<tr><td>Nev</td><td>Munkakor</td><td>Helyiseg</td><td>Belepesi</td><td>Kezdes</td><td>Kilepesi</td><td>Vege</td><td>Ido</td></tr>'
        . '<tr><td>Tört Idő Teszt</td><td>Op</td><td>Uzem</td><td>K1</td><td>2026. jan. 5. 08:00:00</td><td>K1</td><td>2026. jan. 5. 11:57:00</td><td>0.16458333333333</td></tr>'
        . '</table></body></html>';
    $path = worklogFakeXls($html);

    (new WorkLogsImport)->import($path);

    $row = WorkLog::where('nev', 'Tört Idő Teszt')->first();
    expect($row->ido)->toBe('3:57');

    unlink($path);
});

it('formatIdo leaves an already-formatted H:MM string untouched and passes through empty values', function () {
    expect(WorkLogsImport::formatIdo('3:57'))->toBe('3:57');
    expect(WorkLogsImport::formatIdo(''))->toBe('');
    expect(WorkLogsImport::formatIdo(null))->toBeNull();
    expect(WorkLogsImport::formatIdo('0.5'))->toBe('12:00');
});

it('auto-matches an employee by exact name, case-insensitively, without needing manual assignment', function () {
    $company = Company::create(['name' => 'Teszt Kft.']);
    Employee::create(['name' => 'Kovács János', 'company_id' => $company->id]);

    $html = '<html><body><table>'
        . '<tr><td>Nev</td><td>Munkakor</td><td>Helyiseg</td><td>Belepesi</td><td>Kezdes</td><td>Kilepesi</td><td>Vege</td><td>Ido</td></tr>'
        . '<tr><td>kovács jános</td><td>Operátor</td><td>Üzem</td><td>Kapu 1</td><td>2026. jan. 5. 08:00:00</td><td>Kapu 1</td><td>2026. jan. 5. 16:00:00</td><td>08:00</td></tr>'
        . '</table></body></html>';
    $path = worklogFakeXls($html);

    $import = new WorkLogsImport;
    expect($import->unmatchedNames($path))->toBe([]);

    $count = $import->import($path);
    expect($count)->toBe(1);

    $row = WorkLog::where('nev', 'kovács jános')->first();
    expect($row->employee_id)->toBe(Employee::where('name', 'Kovács János')->value('id'));

    unlink($path);
});

it('lists a name as unmatched when no employee matches, and leaves it employee-less without an override', function () {
    $html = '<html><body><table>'
        . '<tr><td>Nev</td><td>Munkakor</td><td>Helyiseg</td><td>Belepesi</td><td>Kezdes</td><td>Kilepesi</td><td>Vege</td><td>Ido</td></tr>'
        . '<tr><td>Ismeretlen Dolgozó</td><td>Operátor</td><td>Üzem</td><td>Kapu 1</td><td>2026. jan. 5. 08:00:00</td><td>Kapu 1</td><td>2026. jan. 5. 16:00:00</td><td>08:00</td></tr>'
        . '</table></body></html>';
    $path = worklogFakeXls($html);

    $import = new WorkLogsImport;
    expect($import->unmatchedNames($path))->toBe(['Ismeretlen Dolgozó']);

    $import->import($path);

    $row = WorkLog::where('nev', 'Ismeretlen Dolgozó')->first();
    expect($row)->not->toBeNull();
    expect($row->employee_id)->toBeNull();

    unlink($path);
});

it('assigns the employee chosen via the manual override map for an unmatched name', function () {
    $company = Company::create(['name' => 'Teszt Kft.']);
    $employee = Employee::create(['name' => 'Teljesen Más Név', 'company_id' => $company->id]);

    $html = '<html><body><table>'
        . '<tr><td>Nev</td><td>Munkakor</td><td>Helyiseg</td><td>Belepesi</td><td>Kezdes</td><td>Kilepesi</td><td>Vege</td><td>Ido</td></tr>'
        . '<tr><td>Ismeretlen Dolgozó</td><td>Operátor</td><td>Üzem</td><td>Kapu 1</td><td>2026. jan. 5. 08:00:00</td><td>Kapu 1</td><td>2026. jan. 5. 16:00:00</td><td>08:00</td></tr>'
        . '</table></body></html>';
    $path = worklogFakeXls($html);

    $import = new WorkLogsImport;
    $import->import($path, ['Ismeretlen Dolgozó' => $employee->id]);

    $row = WorkLog::where('nev', 'Ismeretlen Dolgozó')->first();
    expect($row->employee_id)->toBe($employee->id);

    unlink($path);
});
