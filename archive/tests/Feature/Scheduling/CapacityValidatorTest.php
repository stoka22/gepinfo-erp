<?php

use App\Models\Company;
use App\Models\Machine;
use App\Models\MachineBlocking;
use App\Models\MachineCalendar;
use App\Services\Scheduling\CapacityValidator;

function makeMachine(): Machine
{
    $company = Company::create(['name' => 'Test Kft.']);

    return Machine::create(['name' => 'CNC-1', 'code' => 'GEP-'.uniqid(), 'company_id' => $company->id]);
}

it('rejects when there is no calendar entry for the machine/date', function () {
    $machine = makeMachine();
    $validator = new CapacityValidator();

    expect($validator->hasCapacity($machine->id, '2026-01-05 09:00', '2026-01-05 12:00'))->toBeFalse();
});

it('accepts a request that fits within the daily capacity', function () {
    $machine = makeMachine();
    MachineCalendar::forceCreate(['machine_id' => $machine->id, 'work_date' => '2026-01-05', 'capacity_minutes' => 480]);
    $validator = new CapacityValidator();

    expect($validator->hasCapacity($machine->id, '2026-01-05 09:00', '2026-01-05 12:00'))->toBeTrue();
});

it('rejects a request that exceeds the daily capacity', function () {
    $machine = makeMachine();
    MachineCalendar::forceCreate(['machine_id' => $machine->id, 'work_date' => '2026-01-05', 'capacity_minutes' => 100]);
    $validator = new CapacityValidator();

    // 09:00-12:00 = 180 perc > 100 perc kapacitás
    expect($validator->hasCapacity($machine->id, '2026-01-05 09:00', '2026-01-05 12:00'))->toBeFalse();
});

it('adds setup minutes to the requested duration', function () {
    $machine = makeMachine();
    MachineCalendar::forceCreate(['machine_id' => $machine->id, 'work_date' => '2026-01-05', 'capacity_minutes' => 60]);
    $validator = new CapacityValidator();

    // 09:00-09:30 = 30 perc + 40 perc setup = 70 perc > 60 perc kapacitás
    expect($validator->hasCapacity($machine->id, '2026-01-05 09:00', '2026-01-05 09:30', 40))->toBeFalse();
    // setup nélkül simán beleférne
    expect($validator->hasCapacity($machine->id, '2026-01-05 09:00', '2026-01-05 09:30'))->toBeTrue();
});

it('subtracts overlapping blocked time from the requested duration (current behavior)', function () {
    // Megjegyzés: a jelenlegi implementáció a blokkolt (karbantartási) perceket
    // levonja a KÉRT időtartamból, nem a rendelkezésre álló kapacitásból.
    // Emiatt egy teljesen lefedő blokkolás a kért időtartamot nullára csökkenti,
    // és a kapacitásellenőrzés átmegy még akkor is, ha a gép ezalatt le van tiltva.
    // Ez a teszt a jelenlegi (vitatható) viselkedést rögzíti regresszió ellen.
    $machine = makeMachine();
    MachineCalendar::forceCreate(['machine_id' => $machine->id, 'work_date' => '2026-01-05', 'capacity_minutes' => 100]);
    MachineBlocking::create([
        'machine_id' => $machine->id,
        'starts_at' => '2026-01-05 09:00:00',
        'ends_at' => '2026-01-05 12:00:00',
        'reason' => 'karbantartás',
    ]);
    $validator = new CapacityValidator();

    // 09:00-12:00 = 180 perc kérés, de a teljes ablak blokkolt -> netRequested = 0
    expect($validator->hasCapacity($machine->id, '2026-01-05 09:00', '2026-01-05 12:00'))->toBeTrue();
});
