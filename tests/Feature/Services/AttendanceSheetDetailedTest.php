<?php

use App\Enums\TimeEntryStatus;
use App\Enums\TimeEntryType;
use App\Models\Company;
use App\Models\Employee;
use App\Models\TimeEntry;
use App\Services\AttendanceSheetService;
use Carbon\CarbonImmutable;

it('lists each check-in/check-out segment of a day separately, alongside the aggregated first/last summary', function () {
    $company = Company::create(['name' => 'Szegmens Kft.']);
    $employee = Employee::create(['name' => 'Szegmens Teszt', 'company_id' => $company->id]);

    // Délelőtti szakasz
    TimeEntry::create([
        'employee_id' => $employee->id,
        'company_id'  => $company->id,
        'type'        => TimeEntryType::Presence->value,
        'status'      => TimeEntryStatus::CheckedOut->value,
        'start_date'  => '2026-03-10',
        'start_time'  => '08:00:00',
        'end_date'    => '2026-03-10',
        'end_time'    => '12:00:00',
    ]);
    // Ebédszünet utáni délutáni szakasz, ugyanazon a napon
    TimeEntry::create([
        'employee_id' => $employee->id,
        'company_id'  => $company->id,
        'type'        => TimeEntryType::Presence->value,
        'status'      => TimeEntryStatus::CheckedOut->value,
        'start_date'  => '2026-03-10',
        'start_time'  => '12:30:00',
        'end_date'    => '2026-03-10',
        'end_time'    => '16:30:00',
    ]);

    $service = app(AttendanceSheetService::class);
    $sheet = $service->buildForEmployee(
        $employee,
        CarbonImmutable::create(2026, 3, 1),
        CarbonImmutable::create(2026, 3, 31),
    );

    $day = collect($sheet['days'])->firstWhere('date', '2026-03-10');

    expect($day['segments'])->toHaveCount(2);
    expect($day['segments'][0]['start'])->toBe('08:00');
    expect($day['segments'][0]['end'])->toBe('12:00');
    expect($day['segments'][0]['hoursLabel'])->toBe('4:00');
    expect($day['segments'][1]['start'])->toBe('12:30');
    expect($day['segments'][1]['end'])->toBe('16:30');
    expect($day['segments'][1]['hoursLabel'])->toBe('4:00');

    // Az összevont (nem részletes) nézet mezői változatlanul a legkorábbi be-/legkésőbbi
    // kilépést mutatják — a részletes adat ehhez képest KIEGÉSZÍTÉS, nem csere.
    expect($day['start'])->toBe('08:00');
    expect($day['end'])->toBe('16:30');
});

it('never rounds the first segment start and uses the employee\'s own quota for the day totals', function () {
    $company = Company::create(['name' => 'Kvóta Kft.']);
    $employee = Employee::create(['name' => 'Kvóta Teszt', 'company_id' => $company->id, 'daily_quota_hours' => 6.00]);

    // Nap első szakasza: NEM kerekített. 05:50-12:30 = 6:40, ami a 6 órás dolgozó küszöbén
    // (6:00 kvóta + 0:30 puffer = 6:30) 10 perccel van túl — pont a türelmi időn belül,
    // tehát a "rendes" órák a küszöbig teltnek számítanak, túlóra/hiány nincs.
    TimeEntry::create([
        'employee_id' => $employee->id, 'company_id' => $company->id,
        'type' => TimeEntryType::Presence->value, 'status' => TimeEntryStatus::CheckedOut->value,
        'start_date' => '2026-03-10', 'start_time' => '05:50:00',
        'end_date' => '2026-03-10', 'end_time' => '12:30:00',
    ]);

    $service = app(AttendanceSheetService::class);
    $sheet = $service->buildForEmployee(
        $employee,
        CarbonImmutable::create(2026, 3, 1),
        CarbonImmutable::create(2026, 3, 31),
    );

    $day = collect($sheet['days'])->firstWhere('date', '2026-03-10');

    expect($day['segments'][0]['start'])->toBe('05:50'); // nyers, nem kerekített
    expect($day['start'])->toBe('05:50');
    expect($day['hoursLabel'])->toBe('6:30'); // 6 órás dolgozó küszöbe (6:00 kvóta + 0:30 puffer)
    expect($day['overtimeLabel'])->toBe('0:00');
});

it('gives an empty segments array for a day with no presence entries', function () {
    $company = Company::create(['name' => 'Üres Kft.']);
    $employee = Employee::create(['name' => 'Üres Teszt', 'company_id' => $company->id]);

    $service = app(AttendanceSheetService::class);
    $sheet = $service->buildForEmployee(
        $employee,
        CarbonImmutable::create(2026, 3, 1),
        CarbonImmutable::create(2026, 3, 31),
    );

    $day = collect($sheet['days'])->firstWhere('date', '2026-03-10');
    expect($day['segments'])->toBe([]);
});

it('renders the detailed attendance sheet PDF export view with segment rows', function () {
    $company = Company::create(['name' => 'Render Kft.']);
    $employee = Employee::create(['name' => 'Render Teszt', 'company_id' => $company->id]);

    TimeEntry::create([
        'employee_id' => $employee->id, 'company_id' => $company->id,
        'type' => TimeEntryType::Presence->value, 'status' => TimeEntryStatus::CheckedOut->value,
        'start_date' => '2026-03-10', 'start_time' => '08:00:00',
        'end_date' => '2026-03-10', 'end_time' => '12:00:00',
    ]);
    TimeEntry::create([
        'employee_id' => $employee->id, 'company_id' => $company->id,
        'type' => TimeEntryType::Presence->value, 'status' => TimeEntryStatus::CheckedOut->value,
        'start_date' => '2026-03-10', 'start_time' => '12:30:00',
        'end_date' => '2026-03-10', 'end_time' => '16:30:00',
    ]);

    $service = app(AttendanceSheetService::class);
    $sheet = $service->buildForEmployee(
        $employee->loadMissing('company'),
        CarbonImmutable::create(2026, 3, 1),
        CarbonImmutable::create(2026, 3, 31),
    );

    $html = view('exports.attendance-sheet-detailed', ['sheets' => [$sheet], 'printedAt' => '2026-03-31 10:00'])->render();

    expect($html)->toContain('08:00');
    expect($html)->toContain('12:00');
    expect($html)->toContain('12:30');
    expect($html)->toContain('16:30');
    expect($html)->toContain('Render Teszt');
});
