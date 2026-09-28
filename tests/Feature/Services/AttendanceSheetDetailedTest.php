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

it('shows the actual latest checkout as the aggregated "end", not whichever entry happens to sort last by start_time -- regression for Égi Ferenc 2026-08-27', function () {
    // Élesben reprodukálva: a nap egy teljes műszakot lefedő fő bejegyzésből (05:55-14:30) és
    // több, a műszakon BELÜLI rövid töredékből áll (egy több-olvasós telephely kapu-
    // áthaladásai). A régi kód a $entriesToday lekérdezés `orderBy('start_time')` sorrendjének
    // UTOLSÓ elemét vette "legkésőbbi kilépésnek" -- mivel az utolsó töredék (13:39 rögzített,
    // fél órára kerekítve 14:00-ra) start_time-ja a legmagasabb, ez lett kiválasztva, a
    // kijelzett "Vége" (14:05) így ELLENTMONDOTT a mellette számolt "Ledolgozott" (8:30)
    // oszlopnak, ami valójában a 05:55-14:30 fő műszakon alapul.
    $company = Company::create(['name' => 'Beágyazott Kilépés Kft.']);
    $employee = Employee::create(['name' => 'Beágyazott Kilépés Teszt', 'company_id' => $company->id, 'daily_quota_hours' => 8.00]);

    TimeEntry::create([
        'employee_id' => $employee->id, 'company_id' => $company->id,
        'type' => TimeEntryType::Presence->value, 'status' => TimeEntryStatus::CheckedOut->value,
        'start_date' => '2026-08-27', 'start_time' => '05:55:00',
        'end_date' => '2026-08-27', 'end_time' => '14:30:00',
    ]);

    // Néhány töredék -- a legutolsó (13:39-14:05) start_time-ja (kerekítve 14:00) magasabb,
    // mint a fő bejegyzésé (05:55), tehát start_time szerint ez "utolsó", de a kilépése
    // (14:05) korábbi, mint a fő bejegyzésé (14:30).
    foreach ([['09:00:00', '09:20:00'], ['13:39:00', '14:00:00']] as [$s, $e]) {
        TimeEntry::create([
            'employee_id' => $employee->id, 'company_id' => $company->id,
            'type' => TimeEntryType::Presence->value, 'status' => TimeEntryStatus::CheckedOut->value,
            'start_date' => '2026-08-27', 'raw_start_time' => $s, 'start_time' => $s,
            'end_date' => '2026-08-27', 'end_time' => $e,
        ]);
    }

    $service = app(AttendanceSheetService::class);
    $sheet = $service->buildForEmployee(
        $employee,
        CarbonImmutable::create(2026, 8, 1),
        CarbonImmutable::create(2026, 8, 31),
    );

    $day = collect($sheet['days'])->firstWhere('date', '2026-08-27');

    expect($day['end'])->toBe('14:30');
    expect($day['hoursLabel'])->toBe('8:30');
    expect($day['overtimeLabel'])->toBe('0:00');
});

it('displays the raw (unrounded) arrival time, but calculates the worked hours/overtime from the half-hour-rounded "műszakkezdés"', function () {
    $company = Company::create(['name' => 'Kvóta Kft.']);
    $employee = Employee::create(['name' => 'Kvóta Teszt', 'company_id' => $company->id, 'daily_quota_hours' => 6.00]);

    // Nap első szakasza: a KIJELZETT érkezés nyers (05:20), de a SZÁMÍTÁS a fél órára
    // kerekített műszakkezdéstől (05:30) indul. 05:30-12:30 = 7:00, ami a 6 órás dolgozó
    // küszöbén (6:00 kvóta + 0:30 puffer = 6:30) 30 perccel túl van, a türelmi időn (10 perc)
    // is túl -> a teljes eltérés (30 perc) túlórának számít.
    TimeEntry::create([
        'employee_id' => $employee->id, 'company_id' => $company->id,
        'type' => TimeEntryType::Presence->value, 'status' => TimeEntryStatus::CheckedOut->value,
        'start_date' => '2026-03-10', 'start_time' => '05:20:00',
        'end_date' => '2026-03-10', 'end_time' => '12:30:00',
    ]);

    $service = app(AttendanceSheetService::class);
    $sheet = $service->buildForEmployee(
        $employee,
        CarbonImmutable::create(2026, 3, 1),
        CarbonImmutable::create(2026, 3, 31),
    );

    $day = collect($sheet['days'])->firstWhere('date', '2026-03-10');

    expect($day['segments'][0]['start'])->toBe('05:20'); // kijelzés: nyers, nem kerekített
    expect($day['start'])->toBe('05:20');
    expect($day['hoursLabel'])->toBe('6:30'); // rendes órák a küszöbig (6 órás dolgozó: 6:00 kvóta + 0:30 puffer)
    expect($day['overtimeLabel'])->toBe('0:30'); // számítás: 05:30 (kerekített műszakkezdés) -12:30 = 7:00, 30 perc a küszöb felett
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

it('treats vacation as stronger than presence on the same day: no negative overtime, the presence counts fully as overtime, and vacation credits 8 hours to the monthly total even with no presence at all', function () {
    $company = Company::create(['name' => 'Szabadság Kft.']);
    $employee = Employee::create(['name' => 'Szabadság Teszt', 'company_id' => $company->id]);

    // 2026-03-10: szabadság ÉS jelenlét is van rögzítve ugyanarra a napra (pl. bejött egy
    // rövid időre). A szabadság az "erősebb" -- nem keletkezhet negatív túlóra amiatt, hogy
    // 2 óra jóval a napi küszöb (8:30) alatt van; a 2 óra a TELJES egészében túlórának számít.
    TimeEntry::create([
        'employee_id' => $employee->id, 'company_id' => $company->id,
        'type' => TimeEntryType::Vacation->value, 'status' => TimeEntryStatus::Approved->value,
        'start_date' => '2026-03-10', 'end_date' => '2026-03-10',
    ]);
    TimeEntry::create([
        'employee_id' => $employee->id, 'company_id' => $company->id,
        'type' => TimeEntryType::Presence->value, 'status' => TimeEntryStatus::CheckedOut->value,
        'start_date' => '2026-03-10', 'start_time' => '08:00:00',
        'end_date' => '2026-03-10', 'end_time' => '10:00:00',
    ]);

    // 2026-03-11: TISZTA szabadság nap, jelenlét nélkül -- ennek is 8 órával kell
    // hozzájárulnia a havi ledolgozott-óra összesítőhöz.
    TimeEntry::create([
        'employee_id' => $employee->id, 'company_id' => $company->id,
        'type' => TimeEntryType::Vacation->value, 'status' => TimeEntryStatus::Approved->value,
        'start_date' => '2026-03-11', 'end_date' => '2026-03-11',
    ]);

    $service = app(AttendanceSheetService::class);
    $sheet = $service->buildForEmployee(
        $employee,
        CarbonImmutable::create(2026, 3, 1),
        CarbonImmutable::create(2026, 3, 31),
    );

    $dayWithPresence = collect($sheet['days'])->firstWhere('date', '2026-03-10');
    expect($dayWithPresence['note'])->toBe('Szabadság');
    expect($dayWithPresence['hoursLabel'])->toBe('8:00'); // a szabadság fix 8 órája
    expect($dayWithPresence['overtimeLabel'])->toBe('2:00'); // NEM negatív -- a jelenlét teljes egészében túlóra

    $pureVacationDay = collect($sheet['days'])->firstWhere('date', '2026-03-11');
    expect($pureVacationDay['note'])->toBe('Szabadság');
    expect($pureVacationDay['hoursLabel'])->toBe('8:00');
    expect($pureVacationDay['overtimeLabel'])->toBe('0:00');

    // Havi összesítő: (8:00 szabadság + 2:00 jelenlét) + (8:00 szabadság) = 18:00 ledolgozott;
    // túlóra: 2:00 (a jelenlétből) + 0:00 (tiszta szabadság nap) = 2:00.
    expect($sheet['workedHours']['monthly'])->toBe('18:00');
    expect($sheet['overtime']['monthly'])->toBe('2:00');
});

it('excludes the daily 30-minute break from the monthly "ledolgozott" (worked) total, but only on days a full shift was worked -- the Rendes/Túlóra columns and overtime threshold stay untouched', function () {
    $company = Company::create(['name' => 'Szünet Kft.']);
    $employee = Employee::create(['name' => 'Szünet Teszt', 'company_id' => $company->id]);

    // 2026-03-10: teljes műszak, pontosan a küszöbön (8:30 = kvóta 8:00 + 0:30 puffer) --
    // eléri a napi kvótát, tehát a "ledolgozott"-ból levonódik a 30 perces szünet: 8:00.
    TimeEntry::create([
        'employee_id' => $employee->id, 'company_id' => $company->id,
        'type' => TimeEntryType::Presence->value, 'status' => TimeEntryStatus::CheckedOut->value,
        'start_date' => '2026-03-10', 'start_time' => '08:00:00',
        'end_date' => '2026-03-10', 'end_time' => '16:30:00',
    ]);

    // 2026-03-11: rövid, félnapos jelenlét (3:00) -- ez nem éri el a napi kvótát (8:00), tehát
    // nincs feltételezett ebédszünet, a "ledolgozott" a teljes nyers 3:00 marad.
    TimeEntry::create([
        'employee_id' => $employee->id, 'company_id' => $company->id,
        'type' => TimeEntryType::Presence->value, 'status' => TimeEntryStatus::CheckedOut->value,
        'start_date' => '2026-03-11', 'start_time' => '08:00:00',
        'end_date' => '2026-03-11', 'end_time' => '11:00:00',
    ]);

    $service = app(AttendanceSheetService::class);
    $sheet = $service->buildForEmployee(
        $employee,
        CarbonImmutable::create(2026, 3, 1),
        CarbonImmutable::create(2026, 3, 31),
    );

    $fullDay = collect($sheet['days'])->firstWhere('date', '2026-03-10');
    // A "Rendes"/"Túlóra" oszlopok és a túlóra-küszöb VÁLTOZATLANOK (kvóta+puffer ellen mérnek).
    expect($fullDay['hoursLabel'])->toBe('8:30');
    expect($fullDay['overtimeLabel'])->toBe('0:00');

    $shortDay = collect($sheet['days'])->firstWhere('date', '2026-03-11');
    expect($shortDay['hoursLabel'])->toBe('3:00');
    expect($shortDay['overtimeLabel'])->toBe('-5:30'); // 3:00-8:30 küszöb -- változatlan logika

    // Havi "ledolgozott": teljes napon 8:30-0:30 szünet=8:00; rövid napon nincs levonás=3:00 -> 11:00.
    expect($sheet['workedHours']['monthly'])->toBe('11:00');
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

it('caps the "yearly" overtime/worked-hours summary at the report period, not the whole calendar year -- regression for the "always shows the same total every month" bug', function () {
    $company = Company::create(['name' => 'Éves Göngyöleg Kft.']);
    $employee = Employee::create(['name' => 'Éves Göngyöleg Teszt', 'company_id' => $company->id]);

    // Augusztusban +1:00 túlóra egy napon.
    TimeEntry::create([
        'employee_id' => $employee->id, 'company_id' => $company->id,
        'type' => TimeEntryType::Presence->value, 'status' => TimeEntryStatus::CheckedOut->value,
        'start_date' => '2026-08-10', 'start_time' => '08:00:00',
        'end_date' => '2026-08-10', 'end_time' => '17:30:00', // 9:30 -> 1:00 túlóra a 8:30-as küszöb felett
    ]);

    // Szeptemberben egy MÁSIK, külön +2:00 túlóra egy napon -- ez a "jövőbeli" hónap
    // szempontjából az augusztusi ívhez képest.
    TimeEntry::create([
        'employee_id' => $employee->id, 'company_id' => $company->id,
        'type' => TimeEntryType::Presence->value, 'status' => TimeEntryStatus::CheckedOut->value,
        'start_date' => '2026-09-10', 'start_time' => '08:00:00',
        'end_date' => '2026-09-10', 'end_time' => '18:30:00', // 10:30 -> 2:00 túlóra
    ]);

    $service = app(AttendanceSheetService::class);

    $augustSheet = $service->buildForEmployee($employee, CarbonImmutable::create(2026, 8, 1), CarbonImmutable::create(2026, 8, 31));
    $septemberSheet = $service->buildForEmployee($employee, CarbonImmutable::create(2026, 9, 1), CarbonImmutable::create(2026, 9, 30));

    // Az augusztusi ív éves összesítője csak az augusztusi (+1:00) túlórát látja -- a
    // szeptemberi (+2:00) MÉG NEM történt meg "augusztus végéig göngyölve" nézőpontból.
    expect($augustSheet['overtime']['yearly'])->toBe('1:00');
    // A szeptemberi ív éves összesítője viszont MÁR mindkettőt látja: 1:00 + 2:00 = 3:00.
    expect($septemberSheet['overtime']['yearly'])->toBe('3:00');
    // A kettőnek KÜLÖNBÖZNIE kell -- ez volt a hiba lényege (korábban mindkettő 3:00-t
    // mutatott volna, mert a számítás mindig a teljes naptári évet összegezte).
    expect($augustSheet['overtime']['yearly'])->not->toBe($septemberSheet['overtime']['yearly']);
});

it('adds a "Csúszó" line to the printed sheet when the month\'s net overtime is negative, and omits it otherwise', function () {
    // FONTOS: a cég/dolgozó nevében szándékosan NEM szerepel a "Csúszó" szó -- egy
    // korábbi verzióban ez véletlenül volt benne, és a "Munkáltató: ... Kft." sorban
    // való puszta megjelenése hamis pozitívan "megtalálta" volna a toContain('Csúszó')
    // asszerciót, függetlenül a tényleges feltételes logikától.
    $company = Company::create(['name' => 'Hiány Kft.']);
    $employee = Employee::create(['name' => 'Hiány Teszt', 'company_id' => $company->id]);

    // Rövid hónap: egyetlen, jóval a küszöb alatti nap -> negatív havi túlóra-egyenleg.
    TimeEntry::create([
        'employee_id' => $employee->id, 'company_id' => $company->id,
        'type' => TimeEntryType::Presence->value, 'status' => TimeEntryStatus::CheckedOut->value,
        'start_date' => '2026-05-05', 'start_time' => '08:00:00',
        'end_date' => '2026-05-05', 'end_time' => '11:00:00', // 3:00 -> jóval a 8:30-as küszöb alatt
    ]);

    $service = app(AttendanceSheetService::class);
    $deficitSheet = $service->buildForEmployee($employee, CarbonImmutable::create(2026, 5, 1), CarbonImmutable::create(2026, 5, 31));

    expect($deficitSheet['overtime']['monthlyMinutes'])->toBeLessThan(0);

    $deficitHtml = view('exports.attendance-sheet', ['sheets' => [$deficitSheet], 'printedAt' => '2026-05-31 10:00'])->render();
    expect($deficitHtml)->toContain('Csúszó');

    // Pozitív/nulla hónapnál NEM jelenik meg a "Csúszó" sor.
    $emptyEmployee = Employee::create(['name' => 'Nulla Egyenleg Teszt', 'company_id' => $company->id]);
    $flatSheet = $service->buildForEmployee($emptyEmployee, CarbonImmutable::create(2026, 5, 1), CarbonImmutable::create(2026, 5, 31));
    expect($flatSheet['overtime']['monthlyMinutes'])->toBe(0);
    $flatHtml = view('exports.attendance-sheet', ['sheets' => [$flatSheet], 'printedAt' => '2026-05-31 10:00'])->render();
    expect($flatHtml)->not->toContain('Csúszó');
});

it('caps "Felhasznált" vacation days at the report period, not the whole calendar year -- same bug class as the yearly overtime cap', function () {
    $company = Company::create(['name' => 'Szabi Göngyöleg Kft.']);
    $employee = Employee::create(['name' => 'Szabi Göngyöleg Teszt', 'company_id' => $company->id]);

    \App\Models\VacationBalance::create([
        'employee_id' => $employee->id, 'company_id' => $company->id, 'year' => 2026,
        'base_days' => 20, 'age_extra_days' => 0, 'carried_over_days' => 0, 'manual_adjustment_days' => 0,
    ]);

    // Márciusban 3 munkanap szabadság (10-12., kedd-csütörtök).
    TimeEntry::create([
        'employee_id' => $employee->id, 'company_id' => $company->id,
        'type' => TimeEntryType::Vacation->value, 'status' => TimeEntryStatus::Approved->value,
        'start_date' => '2026-03-10', 'end_date' => '2026-03-12',
    ]);

    // Szeptemberben egy MÁSIK, külön 2 munkanap szabadság -- ez a "jövőbeli" hónap a
    // márciusi ívhez képest.
    TimeEntry::create([
        'employee_id' => $employee->id, 'company_id' => $company->id,
        'type' => TimeEntryType::Vacation->value, 'status' => TimeEntryStatus::Approved->value,
        'start_date' => '2026-09-07', 'end_date' => '2026-09-08',
    ]);

    $service = app(AttendanceSheetService::class);

    $marchSheet = $service->buildForEmployee($employee, CarbonImmutable::create(2026, 3, 1), CarbonImmutable::create(2026, 3, 31));
    $septemberSheet = $service->buildForEmployee($employee, CarbonImmutable::create(2026, 9, 1), CarbonImmutable::create(2026, 9, 30));

    // A márciusi ív csak a márciusi 3 napot látja -- a szeptemberi 2 nap MÉG NEM
    // történt meg "március végéig göngyölve" nézőpontból.
    expect($marchSheet['vacation']['used'])->toBe(3.0);
    // A szeptemberi ív már mindkettőt látja: 3 + 2 = 5.
    expect($septemberSheet['vacation']['used'])->toBe(5.0);
    expect($marchSheet['vacation']['used'])->not->toBe($septemberSheet['vacation']['used']);

    // "Kivehető" az adott riport-időszakra jellemző fennmaradó keret, nem a teljes éves felhasználásból számolt.
    expect($marchSheet['vacation']['remaining'])->toBe(17.0);
    expect($septemberSheet['vacation']['remaining'])->toBe(15.0);
});
