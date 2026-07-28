<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\WorkLog;

function workLogsCommandFakeXls(string $html): string
{
    $path = tempnam(sys_get_temp_dir(), 'work_logs_cmd_test_') . '.xls';
    file_put_contents($path, $html);

    return $path;
}

it('reports a summary and does not write anything in --dry mode', function () {
    $company = Company::create(['name' => 'CLI Teszt Kft.']);
    Employee::create(['name' => 'Ismert Dolgozó', 'company_id' => $company->id]);

    $html = '<html><body><table>'
        . '<tr><td>Nev</td><td>Munkakor</td><td>Helyiseg</td><td>Belepesi</td><td>Kezdes</td><td>Kilepesi</td><td>Vege</td><td>Ido</td></tr>'
        . '<tr><td>Ismert Dolgozó</td><td>Op</td><td>Uzem</td><td>K1</td><td>2026. jan. 5. 08:00:00</td><td>K1</td><td>2026. jan. 5. 16:00:00</td><td>08:00</td></tr>'
        . '<tr><td>Ismeretlen Dolgozó</td><td>Op</td><td>Uzem</td><td>K2</td><td>2026. jan. 6. 08:00:00</td><td>K2</td><td>2026. jan. 6. 16:00:00</td><td>08:00</td></tr>'
        . '</table></body></html>';
    $path = workLogsCommandFakeXls($html);

    $this->artisan('work-logs:import', ['file' => $path, '--dry' => true])
        ->expectsOutputToContain('Beolvasva: 2 sor.')
        ->expectsOutputToContain('Nem azonosítható nevek: 1')
        ->expectsOutputToContain('Ismeretlen Dolgozó')
        ->expectsOutputToContain('Dry-run, nincs írás.')
        ->assertSuccessful();

    expect(WorkLog::count())->toBe(0);

    unlink($path);
});

it('imports rows for real when confirmed, leaving unmatched names without an employee', function () {
    $company = Company::create(['name' => 'CLI Teszt Kft.']);
    $matched = Employee::create(['name' => 'Ismert Dolgozó', 'company_id' => $company->id]);

    $html = '<html><body><table>'
        . '<tr><td>Nev</td><td>Munkakor</td><td>Helyiseg</td><td>Belepesi</td><td>Kezdes</td><td>Kilepesi</td><td>Vege</td><td>Ido</td></tr>'
        . '<tr><td>Ismert Dolgozó</td><td>Op</td><td>Uzem</td><td>K1</td><td>2026. jan. 5. 08:00:00</td><td>K1</td><td>2026. jan. 5. 16:00:00</td><td>08:00</td></tr>'
        . '<tr><td>Ismeretlen Dolgozó</td><td>Op</td><td>Uzem</td><td>K2</td><td>2026. jan. 6. 08:00:00</td><td>K2</td><td>2026. jan. 6. 16:00:00</td><td>08:00</td></tr>'
        . '</table></body></html>';
    $path = workLogsCommandFakeXls($html);

    $this->artisan('work-logs:import', ['file' => $path])
        ->expectsConfirmation('2 sor importálása a work_logs táblába. Folytatod?', 'yes')
        ->expectsOutputToContain('Kész: 2 sor importálva.')
        ->assertSuccessful();

    expect(WorkLog::where('nev', 'Ismert Dolgozó')->value('employee_id'))->toBe($matched->id);
    expect(WorkLog::where('nev', 'Ismeretlen Dolgozó')->value('employee_id'))->toBeNull();

    unlink($path);
});

it('fails cleanly for a missing file', function () {
    $this->artisan('work-logs:import', ['file' => '/no/such/file.xls'])
        ->assertFailed();
});
