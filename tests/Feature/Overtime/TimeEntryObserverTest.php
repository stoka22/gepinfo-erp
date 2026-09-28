<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\OvertimeBalance;
use App\Models\TimeEntry;
use App\Models\User;

function overtimeEmployee(): Employee
{
    $company = Company::create(['name' => 'Test Kft.']);

    return Employee::create(['name' => 'Dolgozó', 'company_id' => $company->id]);
}

function overtimeUser(int $companyId): User
{
    return User::factory()->create(['company_id' => $companyId]);
}

it('settles the balance when a presence entry is checked out', function () {
    $employee = overtimeEmployee();
    $user = overtimeUser($employee->company_id);

    $entry = TimeEntry::create([
        'employee_id' => $employee->id,
        'company_id' => $employee->company_id,
        'type' => 'presence',
        'status' => 'checked_in',
        'start_date' => '2026-01-05',
        'start_time' => '08:00:00',
        'requested_by' => $user->id,
        'approved_by' => $user->id,
    ]);

    $entry->update([
        'end_date' => '2026-01-05',
        'end_time' => '19:00:00', // 11 óra -> +150 perc túlóra
        'status' => 'checked_out',
    ]);

    $entry->refresh();
    expect((float) $entry->hours)->toBe(11.0);
    expect($entry->overtime_delta_minutes)->toBe(150);
    expect($entry->overtime_settled_at)->not->toBeNull();

    $balance = OvertimeBalance::where('employee_id', $employee->id)->first();
    expect($balance->balance_minutes)->toBe(150);
});

it('reduces the balance below the standard workday and lets it go negative', function () {
    $employee = overtimeEmployee();
    $user = overtimeUser($employee->company_id);

    $entry = TimeEntry::create([
        'employee_id' => $employee->id,
        'company_id' => $employee->company_id,
        'type' => 'presence',
        'status' => 'checked_in',
        'start_date' => '2026-01-05',
        'start_time' => '08:00:00',
        'requested_by' => $user->id,
        'approved_by' => $user->id,
    ]);

    $entry->update([
        'end_date' => '2026-01-05',
        'end_time' => '13:00:00', // 5 óra -> -210 perc
        'status' => 'checked_out',
    ]);

    $balance = OvertimeBalance::where('employee_id', $employee->id)->first();
    expect($balance->balance_minutes)->toBe(-210);
});

it('does not settle an entry flagged for review', function () {
    $employee = overtimeEmployee();
    $user = overtimeUser($employee->company_id);

    $entry = TimeEntry::create([
        'employee_id' => $employee->id,
        'company_id' => $employee->company_id,
        'type' => 'presence',
        'status' => 'checked_in',
        'start_date' => '2026-01-05',
        'start_time' => '08:00:00',
        'requested_by' => $user->id,
        'approved_by' => $user->id,
    ]);

    $entry->update([
        'end_date' => '2026-01-05',
        'end_time' => '20:00:00',
        'status' => 'checked_out',
        'needs_review' => true,
    ]);

    $entry->refresh();
    expect($entry->overtime_settled_at)->toBeNull();
    expect(OvertimeBalance::where('employee_id', $employee->id)->exists())->toBeFalse();

    // Admin jóváhagyja: needs_review lekapcsolása -> ekkor kell elszámolni
    $entry->update(['needs_review' => false]);
    $entry->refresh();

    expect($entry->overtime_settled_at)->not->toBeNull();
    expect($entry->overtime_delta_minutes)->toBe(12 * 60 - 510);

    $balance = OvertimeBalance::where('employee_id', $employee->id)->first();
    expect($balance->balance_minutes)->toBe(12 * 60 - 510);
});

it('reverses the old delta and applies the new one when a settled entry is corrected', function () {
    $employee = overtimeEmployee();
    $user = overtimeUser($employee->company_id);

    $entry = TimeEntry::create([
        'employee_id' => $employee->id,
        'company_id' => $employee->company_id,
        'type' => 'presence',
        'status' => 'checked_in',
        'start_date' => '2026-01-05',
        'start_time' => '08:00:00',
        'requested_by' => $user->id,
        'approved_by' => $user->id,
    ]);

    $entry->update([
        'end_date' => '2026-01-05',
        'end_time' => '19:00:00', // 11 óra, delta=+150
        'status' => 'checked_out',
    ]);

    $balance = OvertimeBalance::where('employee_id', $employee->id)->first();
    expect($balance->balance_minutes)->toBe(150);

    // Admin korrekció: valójában 16:00-kor ment el (8 óra, delta=-30)
    $entry->update(['end_time' => '16:00:00']);
    $entry->refresh();
    $balance->refresh();

    expect($entry->overtime_delta_minutes)->toBe(-30);
    expect($balance->balance_minutes)->toBe(-30);
});

it('aggregates multiple daily check-in/check-out segments into a single day delta', function () {
    $employee = overtimeEmployee();

    // 08:00-12:00 (4h) + 13:00-18:00 (5h) = 9h total -> +30 perc túlóra (nem két külön -270/+? hiba).
    $e1 = TimeEntry::create([
        'employee_id' => $employee->id,
        'company_id' => $employee->company_id,
        'type' => 'presence',
        'status' => 'checked_in',
        'start_date' => '2026-01-05',
        'start_time' => '08:00:00',
    ]);
    $e1->update(['end_date' => '2026-01-05', 'end_time' => '12:00:00', 'status' => 'checked_out']);

    $e2 = TimeEntry::create([
        'employee_id' => $employee->id,
        'company_id' => $employee->company_id,
        'type' => 'presence',
        'status' => 'checked_in',
        'start_date' => '2026-01-05',
        'start_time' => '13:00:00',
    ]);
    $e2->update(['end_date' => '2026-01-05', 'end_time' => '18:00:00', 'status' => 'checked_out']);

    $e1->refresh();
    $e2->refresh();

    expect($e1->overtime_delta_minutes + $e2->overtime_delta_minutes)->toBe(30);

    $balance = OvertimeBalance::where('employee_id', $employee->id)->first();
    expect($balance->balance_minutes)->toBe(30);

    // Utólagos korrekció az egyik szakaszon: a napi összeg helyesen frissül, nem duplázódik.
    $e1->update(['end_time' => '12:15:00']); // +15 perc -> napi delta most +45
    $e1->refresh();
    $e2->refresh();
    $balance->refresh();

    expect($e1->overtime_delta_minutes + $e2->overtime_delta_minutes)->toBe(45);
    expect($balance->balance_minutes)->toBe(45);
});

it('does not settle any segment of a day while another segment still needs review', function () {
    $employee = overtimeEmployee();

    $e1 = TimeEntry::create([
        'employee_id' => $employee->id,
        'company_id' => $employee->company_id,
        'type' => 'presence',
        'status' => 'checked_in',
        'start_date' => '2026-01-05',
        'start_time' => '08:00:00',
    ]);
    $e1->update(['end_date' => '2026-01-05', 'end_time' => '12:00:00', 'status' => 'checked_out']);
    $e1->refresh();
    expect($e1->overtime_settled_at)->not->toBeNull();

    // Második szakasz felülvizsgálatra vár -> a nap már nem zárható le véglegesen.
    $e2 = TimeEntry::create([
        'employee_id' => $employee->id,
        'company_id' => $employee->company_id,
        'type' => 'presence',
        'status' => 'checked_out',
        'start_date' => '2026-01-05',
        'start_time' => '13:00:00',
        'end_date' => '2026-01-05',
        'end_time' => '20:00:00',
        'needs_review' => true,
    ]);

    // Jóváhagyás: mindkét szakasz együttes idejéből számol, nem csak a sajátjából.
    $e2->update(['needs_review' => false]);
    $e1->refresh();
    $e2->refresh();

    expect($e1->overtime_delta_minutes + $e2->overtime_delta_minutes)->toBe(150); // (4h+7h=660perc) - 510
    $balance = OvertimeBalance::where('employee_id', $employee->id)->first();
    expect($balance->balance_minutes)->toBe(150);
});

it('rounds the first check-in of the day to the half hour (the "műszakkezdés") when settling the balance -- also for kiosk-style entries without entry_method', function () {
    $employee = overtimeEmployee(); // alapértelmezett 8 órás kvóta -> küszöb 8:30

    $entry = TimeEntry::create([
        'employee_id' => $employee->id,
        'company_id' => $employee->company_id,
        'type' => 'presence',
        'status' => 'checked_in',
        'start_date' => '2026-01-05',
        'start_time' => '05:20:00', // nap első szakasza, <=30 perc -> műszakkezdés 05:30-ra kerekítve
    ]);

    $entry->update([
        'end_date' => '2026-01-05',
        'end_time' => '14:20:00', // 05:30 (kerekített műszakkezdés) - 14:20 = 8:50 -> +20 perc a küszöb (8:30) felett, a türelmi időn (10 perc) túl
        'status' => 'checked_out',
    ]);

    $entry->refresh();
    // A "hours" mező a KEREKÍTETT műszakkezdéstől számol: 05:30-14:20 = 8:50 = 530 perc = 8.83 óra
    // (nem a nyers 05:20-tól, ami 9:00 = 540 percet adna -- a kijelzett érkezés marad 05:20, de a
    // számítás a műszakkezdéstől indul).
    expect((float) $entry->hours)->toBe(8.83);
    expect($entry->overtime_delta_minutes)->toBe(20); // 530 perc -> 20 perc a küszöb (8:30) felett, a türelmi időn túl -> teljes eltérés számít
});

it('applies a 6-hour employee\'s own overtime threshold instead of the fixed 8:30', function () {
    $company = Company::create(['name' => 'Rész Kft.']);
    $employee = Employee::create(['name' => 'Részmunkaidős', 'company_id' => $company->id, 'daily_quota_hours' => 6.00]);

    $entry = TimeEntry::create([
        'employee_id' => $employee->id,
        'company_id' => $employee->company_id,
        'type' => 'presence',
        'status' => 'checked_in',
        'start_date' => '2026-01-05',
        'start_time' => '08:00:00',
    ]);

    // 08:00-14:41 = 6:41 -> a 6:30-as küszöb felett 11 perccel, ami már a türelmi időn (10 perc) túl van.
    $entry->update([
        'end_date' => '2026-01-05',
        'end_time' => '14:41:00',
        'status' => 'checked_out',
    ]);

    $entry->refresh();
    expect($entry->overtime_delta_minutes)->toBe(11);

    $balance = OvertimeBalance::where('employee_id', $employee->id)->first();
    expect($balance->balance_minutes)->toBe(11);
});

it('rounds only the first segment (the "műszakkezdés") of the day to the half hour, not the second, even when settled independently', function () {
    $employee = overtimeEmployee();

    $morning = TimeEntry::create([
        'employee_id' => $employee->id,
        'company_id' => $employee->company_id,
        'type' => 'presence',
        'status' => 'checked_in',
        'start_date' => '2026-01-05',
        'start_time' => '05:20:00', // első szakasz -> műszakkezdés 05:30-ra kerekítve
    ]);
    $morning->update(['end_date' => '2026-01-05', 'end_time' => '12:00:00', 'status' => 'checked_out']);

    $afternoon = TimeEntry::create([
        'employee_id' => $employee->id,
        'company_id' => $employee->company_id,
        'type' => 'presence',
        'status' => 'checked_in',
        'start_date' => '2026-01-05',
        'start_time' => '12:37:00', // második szakasz -> NEM kerekített
    ]);
    $afternoon->update(['end_date' => '2026-01-05', 'end_time' => '16:00:00', 'status' => 'checked_out']);

    $morning->refresh();
    $afternoon->refresh();

    // 05:30 (kerekített műszakkezdés) -12:00 (6:30) + 12:37-16:00 (3:23) = 9:53 = 593 perc -> 593-510=83 perc túlóra összesen.
    expect($morning->overtime_delta_minutes + $afternoon->overtime_delta_minutes)->toBe(83);
});

it('self-heals a corrupted stored balance to the absolute correct total instead of incrementing the corrupted value -- regression for the 2026-09 173-hour drift found in production', function () {
    // Élesben azonosítva: kötegelt műveleteknél (tömeges import, tömeges admin
    // jóváhagyás) a korábbi, RELATÍV (increment-alapú, applyDelta()) mechanizmus
    // bizonyítottan elcsúszott -- egy dolgozó egyenlege 173 órával tért el a saját
    // bejegyzéseinek tényleges összegétől. Az új mechanizmus minden tényleges
    // változásnál a TELJES adatbázis-állapotból, NULLÁRÓL számolja újra az
    // egyenleget (OvertimeBalanceService::recomputeBalance()) -- ezért egy már
    // korábban elcsúszott/hibás tárolt érték nem halmozódik tovább, hanem a
    // következő valódi elszámolási eseménynél automatikusan a helyes abszolút
    // összegre íródik felül.
    $employee = overtimeEmployee();

    $entry = TimeEntry::create([
        'employee_id' => $employee->id,
        'company_id' => $employee->company_id,
        'type' => 'presence',
        'status' => 'checked_in',
        'start_date' => '2026-01-05',
        'start_time' => '08:00:00',
    ]);
    $entry->update(['end_date' => '2026-01-05', 'end_time' => '19:00:00', 'status' => 'checked_out']);

    $balance = OvertimeBalance::where('employee_id', $employee->id)->first();
    expect($balance->balance_minutes)->toBe(150);

    // Szimuláljuk a korábbi hibát: az egyenleg valahogy egy teljesen hibás értékre
    // csúszott (pl. egy kötegelt művelet duplán/rossz sorrendben alkalmazott
    // deltákat).
    $balance->update(['balance_minutes' => 500000]);

    // Egy VALÓDI korrekció ugyanezen a bejegyzésen (más kilépési idő) kiváltja az
    // újraszámolást.
    $entry->update(['end_time' => '20:00:00']); // 12 óra -> delta = 720-510 = 210
    $entry->refresh();
    $balance->refresh();

    // A hibás 500000 NEM adódik hozzá semmihez -- az egyenleg pontosan a bejegyzés
    // tényleges (egyetlen) deltájára áll vissza, függetlenül a korábban ott tárolt
    // hibás értéktől.
    expect($entry->overtime_delta_minutes)->toBe(210);
    expect($balance->balance_minutes)->toBe(210);
});

it('deducts a banked overtime consumption from the balance only once approved, and corrects it if the hours are later changed', function () {
    $employee = overtimeEmployee();
    $user = overtimeUser($employee->company_id);

    // Kezdő egyenleg egy sima jelenlétből.
    $presence = TimeEntry::create([
        'employee_id' => $employee->id,
        'company_id' => $employee->company_id,
        'type' => 'presence',
        'status' => 'checked_in',
        'start_date' => '2026-01-05',
        'start_time' => '08:00:00',
    ]);
    $presence->update(['end_date' => '2026-01-05', 'end_time' => '19:00:00', 'status' => 'checked_out']); // +150

    $consumption = TimeEntry::create([
        'employee_id' => $employee->id,
        'company_id' => $employee->company_id,
        'type' => 'overtime',
        'status' => 'pending',
        'start_date' => '2026-01-10',
        'end_date' => '2026-01-10',
        'hours' => -5, // 5 óra (a "hours" mező ÓRÁBAN tárol, nem percben) a keret terhére
        'requested_by' => $user->id,
    ]);

    // Amíg 'pending', nem érinti a keretet.
    $balance = OvertimeBalance::where('employee_id', $employee->id)->first();
    expect($balance->balance_minutes)->toBe(150);

    $consumption->update(['status' => 'approved', 'approved_by' => $user->id]);
    $balance->refresh();
    expect($balance->balance_minutes)->toBe(150 - 300);

    // Utólagos korrekció: valójában csak 4 órát vett igénybe.
    $consumption->update(['hours' => -4]);
    $balance->refresh();
    expect($balance->balance_minutes)->toBe(150 - 240);
});

it('ignores non-presence entries entirely', function () {
    $employee = overtimeEmployee();
    $user = overtimeUser($employee->company_id);

    TimeEntry::create([
        'employee_id' => $employee->id,
        'company_id' => $employee->company_id,
        'type' => 'vacation',
        'status' => 'approved',
        'start_date' => '2026-01-05',
        'end_date' => '2026-01-06',
        'requested_by' => $user->id,
        'approved_by' => $user->id,
    ]);

    expect(OvertimeBalance::where('employee_id', $employee->id)->exists())->toBeFalse();
});
