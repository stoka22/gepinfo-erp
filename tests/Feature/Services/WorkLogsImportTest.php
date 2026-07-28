<?php

use App\Imports\WorkLogsImport;
use App\Models\WorkLog;

function worklogFakeXls(string $html): string
{
    $path = tempnam(sys_get_temp_dir(), 'worklog_test_') . '.xls';
    file_put_contents($path, $html);

    return $path;
}

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
