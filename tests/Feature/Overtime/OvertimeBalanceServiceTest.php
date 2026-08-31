<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\OvertimeBalance;
use App\Models\TimeEntry;
use App\Services\Overtime\OvertimeBalanceService;

it('reports the standard workday as 8 hours 30 minutes', function () {
    expect(OvertimeBalanceService::STANDARD_WORKDAY_MINUTES)->toBe(510);
});

it('computes a positive delta above the standard workday and negative below it', function () {
    $service = new OvertimeBalanceService();

    expect($service->deltaMinutes(510))->toBe(0)
        ->and($service->deltaMinutes(600))->toBe(90)
        ->and($service->deltaMinutes(400))->toBe(-110);
});

it('applies tolerance bands around the standard workday', function () {
    $service = new OvertimeBalanceService();

    // korai távozás hibahatáron belül (<=2 perc hiány) -> 0
    expect($service->deltaMinutes(509))->toBe(0)
        ->and($service->deltaMinutes(508))->toBe(0)
        // hibahatáron túli hiány -> valódi negatív eltérés
        ->and($service->deltaMinutes(507))->toBe(-3)
        // túlóra hibahatáron belül (<=10 perc) -> 0
        ->and($service->deltaMinutes(511))->toBe(0)
        ->and($service->deltaMinutes(520))->toBe(0)
        // hibahatáron túli túlóra -> teljes eltérés számít, visszamenőlegesen
        ->and($service->deltaMinutes(521))->toBe(11);
});

it('computes worked minutes handling an overnight shift', function () {
    $company = Company::create(['name' => 'Test Kft.']);
    $employee = Employee::create(['name' => 'Éjszakás', 'company_id' => $company->id]);
    $entry = TimeEntry::forceCreate([
        'employee_id' => $employee->id,
        'company_id' => $company->id,
        'type' => 'presence',
        'status' => 'checked_out',
        'start_date' => '2026-01-05',
        'start_time' => '22:00:00',
        'end_date' => '2026-01-06',
        'end_time' => '06:30:00',
        'needs_review' => true, // ne fusson le a valódi mentési observer itt, csak a nyers számítást teszteljük
    ]);

    $service = new OvertimeBalanceService();

    expect($service->workedMinutes($entry))->toBe(510);
});

it('creates the balance row on first use and allows it to go negative', function () {
    $company = Company::create(['name' => 'Test Kft.']);
    $employee = Employee::create(['name' => 'Dolgozó', 'company_id' => $company->id]);
    $service = new OvertimeBalanceService();

    $service->applyDelta($employee->id, $company->id, -110);
    $balance = OvertimeBalance::where('employee_id', $employee->id)->first();

    expect($balance)->not->toBeNull()
        ->and($balance->balance_minutes)->toBe(-110);

    $service->applyDelta($employee->id, $company->id, 30);
    $balance->refresh();

    expect($balance->balance_minutes)->toBe(-80);
});

it('computes the standard threshold from the employee\'s own daily quota, defaulting to 8 hours', function () {
    $service = new OvertimeBalanceService();
    $company = Company::create(['name' => 'Kvóta Kft.']);

    $sixHour = Employee::create(['name' => '6 órás', 'company_id' => $company->id, 'daily_quota_hours' => 6.00]);
    $fourHour = Employee::create(['name' => '4 órás', 'company_id' => $company->id, 'daily_quota_hours' => 4.00]);
    $eightHour = Employee::create(['name' => '8 órás', 'company_id' => $company->id, 'daily_quota_hours' => 8.00]);

    expect($service->standardMinutesFor($sixHour))->toBe(6 * 60 + 30) // 6:30
        ->and($service->standardMinutesFor($fourHour))->toBe(4 * 60 + 30) // 4:30
        ->and($service->standardMinutesFor($eightHour))->toBe(510) // 8:30
        ->and($service->standardMinutesFor(null))->toBe(510); // nincs dolgozó -> alapérték
});

it('applies the overtime tolerance relative to a 6-hour quota: overtime only from the 10th minute past 6:30', function () {
    $service = new OvertimeBalanceService();
    $standard = $service->standardMinutesFor(
        Employee::create(['name' => '6 órás 2', 'company_id' => Company::create(['name' => 'Türelmi Kft.'])->id, 'daily_quota_hours' => 6.00])
    );

    expect($standard)->toBe(390); // 6:30

    // 6:30-tól +10 percig (6:40-ig) még nem túlóra.
    expect($service->deltaMinutes(390, $standard))->toBe(0)
        ->and($service->deltaMinutes(400, $standard))->toBe(0) // 6:40, még türelmi időn belül
        ->and($service->deltaMinutes(401, $standard))->toBe(11); // 6:41-től a TELJES eltérés (401-390) számít
});

it('rounds only the first segment start to the half hour for the WORKED-MINUTES calculation, but the displayed (effectiveStartLabel) time always stays raw', function () {
    $company = Company::create(['name' => 'Kerekítés Kft.']);
    $employee = Employee::create(['name' => 'Kerekítés Teszt', 'company_id' => $company->id]);
    $service = new OvertimeBalanceService();

    $morning = TimeEntry::forceCreate([
        'employee_id' => $employee->id, 'company_id' => $company->id,
        'type' => 'presence', 'status' => 'checked_out',
        'start_date' => '2026-04-01', 'start_time' => '05:20:00', // <=30 perc -> fél órára kerekítve 05:30 (NEM 06:00 -- ez nem egész órás kerekítés)
        'end_date' => '2026-04-01', 'end_time' => '12:04:00',
        'needs_review' => true, // ne fusson a valódi observer, csak a nyers számítást teszteljük
    ]);
    $afternoon = TimeEntry::forceCreate([
        'employee_id' => $employee->id, 'company_id' => $company->id,
        'type' => 'presence', 'status' => 'checked_out',
        'start_date' => '2026-04-01', 'start_time' => '12:47:00',
        'end_date' => '2026-04-01', 'end_time' => '16:12:00',
        'needs_review' => true,
    ]);

    $minutes = $service->segmentMinutesForDay(collect([$morning, $afternoon]));

    // Reggeli (első, "műszakkezdés") szakasz: SZÁMÍTÁSHOZ fél órára kerekítve 05:30-ra, 05:30-12:04 = 394 perc.
    expect($minutes[spl_object_id($morning)])->toBe(394);
    // Délutáni (második) szakasz: kezdés NEM kerekített, 12:47-16:12 = 205 perc.
    expect($minutes[spl_object_id($afternoon)])->toBe(205);

    // A KIJELZETT érkezési idő (effectiveStartLabel) ettől függetlenül mindig a nyers idő marad.
    expect($service->effectiveStartLabel($morning, true))->toBe('05:20');
    expect($service->effectiveStartLabel($afternoon, false))->toBe('12:47');
});

it('does not treat a short first segment as an overnight shift just because rounding pushed the start past the end', function () {
    // Regresszió: a nyers kezdés (16:13) fél órára kerekítve 16:30 lesz, ami MÁR a tényleges
    // kilépés (16:26) UTÁN van. A javítás előtt ez tévesen "éjszakába nyúló műszaknak" tűnt,
    // és ~24 órát (1436 percet) írt jóvá egy valójában ~13 perces szakaszért.
    $company = Company::create(['name' => 'Rövid Szakasz Kft.']);
    $employee = Employee::create(['name' => 'Rövid Szakasz Teszt', 'company_id' => $company->id]);
    $service = new OvertimeBalanceService();

    $entry = TimeEntry::forceCreate([
        'employee_id' => $employee->id, 'company_id' => $company->id,
        'type' => 'presence', 'status' => 'checked_out',
        'start_date' => '2026-06-19', 'start_time' => '16:13:00',
        'end_date' => '2026-06-19', 'end_time' => '16:26:00',
        'needs_review' => true,
    ]);

    $minutes = $service->segmentMinutesForDay(collect([$entry]));

    expect($minutes[spl_object_id($entry)])->toBe(0);
});

it('still correctly credits a genuine overnight shift recorded with the same start_date and end_date, even with first-segment rounding applied', function () {
    // A presence-űrlapon az end_date mező rejtett, tehát egy valódi éjszakai műszaknál is
    // start_date === end_date marad az adatbázisban – a rendszernek pusztán az időkből kell
    // felismernie az éjfél utáni átnyúlást. Ennek a javítás után is működnie kell.
    $company = Company::create(['name' => 'Éjszakai Kft.']);
    $employee = Employee::create(['name' => 'Éjszakás Teszt', 'company_id' => $company->id]);
    $service = new OvertimeBalanceService();

    $entry = TimeEntry::forceCreate([
        'employee_id' => $employee->id, 'company_id' => $company->id,
        'type' => 'presence', 'status' => 'checked_out',
        'start_date' => '2026-06-19', 'start_time' => '22:07:00',
        'end_date' => '2026-06-19', 'end_time' => '06:10:00',
        'needs_review' => true,
    ]);

    $minutes = $service->segmentMinutesForDay(collect([$entry]));

    // Kerekítve 22:30-ra indul (első szakasz), és másnap 06:10-ig tart -> 7:40 = 460 perc.
    expect($minutes[spl_object_id($entry)])->toBe(460);
});

it('carries the date forward when the first-segment rounding itself crosses midnight', function () {
    // Ha a nyers kezdés 23:31-23:59 közé esik, a fél órás felkerekítés 00:00-t ad vissza –
    // ennek a KÖVETKEZŐ napra kell esnie, nem ugyanarra a napra, különben a kerekített
    // kezdés időben a nyers kezdés ELÉ (visszafelé) csúszna.
    $company = Company::create(['name' => 'Éjfél Kft.']);
    $employee = Employee::create(['name' => 'Éjfél Teszt', 'company_id' => $company->id]);
    $service = new OvertimeBalanceService();

    $entry = TimeEntry::forceCreate([
        'employee_id' => $employee->id, 'company_id' => $company->id,
        'type' => 'presence', 'status' => 'checked_out',
        'start_date' => '2026-06-19', 'start_time' => '23:45:00',
        'end_date' => '2026-06-19', 'end_time' => '07:50:00',
        'needs_review' => true,
    ]);

    $minutes = $service->segmentMinutesForDay(collect([$entry]));

    // Kerekítve másnap 00:00-tól 07:50-ig = 470 perc (NEM 31:50-nyi hamis érték).
    expect($minutes[spl_object_id($entry)])->toBe(470);
});

it('does not double-count a day where a full-shift entry and several short entries nested WITHIN it coexist -- regression for Égi Ferenc 2026-08-27', function () {
    // Élesben reprodukálva: egy több-olvasós telephely (csarnok/raktár közti kapu-áthaladás)
    // granulátumban exportálja a jelenlétet -- EGY teljes műszakot lefedő bejegyzés MELLETT,
    // ugyanazon az idősávon BELÜL, több tucat rövid, néhány perces szakasz jön be külön
    // TimeEntry-ként. A régi (egyszerű összegzés) logika ezeket a fő műszak idejére RÁADÁSKÉNT
    // számolta, +4:11 hamis túlórát eredményezve egy valójában 0:00-ás (8:30-ás) napon.
    $company = Company::create(['name' => 'Beágyazott Szakaszok Kft.']);
    $employee = Employee::create(['name' => 'Több Olvasós Teszt', 'company_id' => $company->id, 'daily_quota_hours' => 8.00]);
    $service = new OvertimeBalanceService();

    $fullShift = TimeEntry::forceCreate([
        'employee_id' => $employee->id, 'company_id' => $company->id,
        'type' => 'presence', 'status' => 'checked_out',
        'start_date' => '2026-08-27', 'start_time' => '05:55:00',
        'end_date' => '2026-08-27', 'end_time' => '14:30:00',
        'needs_review' => true,
    ]);

    $fragments = collect([
        ['08:34:00', '08:40:00'],
        ['08:56:00', '09:16:00'],
        ['09:21:00', '09:34:00'],
        ['09:37:00', '09:39:00'],
        ['09:54:00', '09:55:00'],
        ['09:59:00', '12:42:00'],
        ['12:45:00', '12:48:00'],
        ['13:12:00', '13:15:00'],
        ['13:20:00', '13:34:00'],
        ['13:39:00', '14:05:00'],
    ])->map(fn ($t) => TimeEntry::forceCreate([
        'employee_id' => $employee->id, 'company_id' => $company->id,
        'type' => 'presence', 'status' => 'checked_out',
        'start_date' => '2026-08-27', 'raw_start_time' => $t[0], 'start_time' => $t[0],
        'end_date' => '2026-08-27', 'end_time' => $t[1],
        'needs_review' => true,
    ]));

    $allForDay = $fragments->push($fullShift);

    // Szakaszonként (pl. a részletes ívhez) a régi, nem-unió hossz marad -- ez nem hibás, csak
    // nem szabad a napi küszöbhöz összegezni.
    $segments = $service->segmentMinutesForDay($allForDay);
    expect($segments[spl_object_id($fullShift)])->toBe(510); // 06:00 (kerekítve) - 14:30

    // A NAPI ledolgozott idő viszont az unió hossza: a fő műszak (06:00-14:30) mindent lefed,
    // ami a töredékekben van, tehát a nap teljes ledolgozott ideje MARAD 510 perc (8:30) --
    // nem 761 (a fő műszak + minden töredék naiv összege).
    $total = $service->totalWorkedMinutesForDay($allForDay);
    expect($total)->toBe(510);

    $standard = $service->standardMinutesFor($employee);
    expect($service->deltaMinutes($total, $standard))->toBe(0);
});

it('still sums genuinely sequential, non-overlapping same-day segments normally (e.g. a real lunch break)', function () {
    $company = Company::create(['name' => 'Ebédszünet Kft.']);
    $employee = Employee::create(['name' => 'Ebédszünet Teszt', 'company_id' => $company->id, 'daily_quota_hours' => 8.00]);
    $service = new OvertimeBalanceService();

    $morning = TimeEntry::forceCreate([
        'employee_id' => $employee->id, 'company_id' => $company->id,
        'type' => 'presence', 'status' => 'checked_out',
        'start_date' => '2026-08-27', 'start_time' => '06:00:00',
        'end_date' => '2026-08-27', 'end_time' => '12:00:00',
        'needs_review' => true,
    ]);
    $afternoon = TimeEntry::forceCreate([
        'employee_id' => $employee->id, 'company_id' => $company->id,
        'type' => 'presence', 'status' => 'checked_out',
        'start_date' => '2026-08-27', 'start_time' => '12:30:00',
        'end_date' => '2026-08-27', 'end_time' => '14:30:00',
        'needs_review' => true,
    ]);

    $total = $service->totalWorkedMinutesForDay(collect([$morning, $afternoon]));

    // 06:00-12:00 (360) + 12:30-14:30 (120) = 480, ugyanaz mint a szakaszonkénti összeg,
    // mert a két szakasz nem fed át.
    expect($total)->toBe(480);
});

it('combines the automatic balance with a manual adjustment in the effective balance', function () {
    $company = Company::create(['name' => 'Test Kft.']);
    $employee = Employee::create(['name' => 'Dolgozó', 'company_id' => $company->id]);
    $balance = OvertimeBalance::create([
        'employee_id' => $employee->id,
        'company_id' => $company->id,
        'balance_minutes' => -200,
        'manual_adjustment_minutes' => 50,
    ]);

    expect($balance->effective_balance_minutes)->toBe(-150);
});
