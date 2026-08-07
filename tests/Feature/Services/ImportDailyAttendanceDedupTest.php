<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\TimeEntry;

function dailyAttendanceFakeXls(string $employeeName, string $dateCell, string $startCell, string $endCell): string
{
    $html = '<html><body><table>'
        . "<tr><td>{$employeeName}</td></tr>"
        . '<tr><td>Nap</td><td>Nev</td><td>Kezdés</td><td>Vége</td><td>Munkaidő</td></tr>'
        . "<tr><td>{$dateCell}</td><td></td><td>{$startCell}</td><td>{$endCell}</td><td></td></tr>"
        . '</table></body></html>';

    $path = tempnam(sys_get_temp_dir(), 'daily_attendance_test_') . '.xls';
    file_put_contents($path, $html);

    return $path;
}

it('does not create a duplicate presence entry when the day was already imported by a different source (worklog-import)', function () {
    // Ugyanaz az éles hiba, a másik irányból: ha a work-logs:sync-presence már felvitte a
    // napot (entry_method=worklog-import), a napi bontású import (ImportDailyAttendance)
    // duplikáció-védelme korábban csak a SAJÁT (daily-import) forrása ellen nézett, ezért
    // ismét beszúrt volna egy második, majdnem azonos sort ugyanarra a napra.
    $company = Company::create(['name' => 'Dedup Kft. 2']);
    $employee = Employee::create(['name' => 'Napi Dedup Teszt', 'company_id' => $company->id]);

    TimeEntry::create([
        'employee_id'  => $employee->id,
        'company_id'   => $company->id,
        'type'         => 'presence',
        'status'       => 'checked_out',
        'start_date'   => '2026-01-05',
        'start_time'   => '08:00:00',
        'end_date'     => '2026-01-05',
        'end_time'     => '16:30:19',
        'entry_method' => 'worklog-import',
    ]);

    $path = dailyAttendanceFakeXls('Napi Dedup Teszt', '2026-01-05', '08:00:00', '16:30:00');

    Artisan::call('import:daily-attendance', ['file' => $path, '--employee' => $employee->id]);

    expect(TimeEntry::where('employee_id', $employee->id)->where('type', 'presence')->count())->toBe(1);

    unlink($path);
});
