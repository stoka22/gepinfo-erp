<?php

use App\Models\Company;
use App\Models\Machine;
use App\Models\Task;
use App\Models\TaskDependency;
use App\Services\Scheduling\DependencyValidator;

function makeDependencyMachine(): Machine
{
    $company = Company::create(['name' => 'Test Kft.']);

    return Machine::create(['name' => 'CNC-1', 'code' => 'GEP-'.uniqid(), 'company_id' => $company->id]);
}

function makeTask(Machine $machine, string $starts, string $ends): Task
{
    return Task::create([
        'name' => 'T',
        'machine_id' => $machine->id,
        'starts_at' => $starts,
        'ends_at' => $ends,
    ]);
}

it('passes checkFs when the successor starts after predecessor end + lag', function () {
    $machine = makeDependencyMachine();
    $pred = makeTask($machine, '2026-01-05 08:00:00', '2026-01-05 12:00:00');
    $succ = makeTask($machine, '2026-01-05 12:30:00', '2026-01-05 14:00:00');
    TaskDependency::create(['predecessor_id' => $pred->id, 'successor_id' => $succ->id, 'lag_minutes' => 30]);

    expect((new DependencyValidator())->checkFs($succ))->toBeTrue();
});

it('fails checkFs when the successor starts before predecessor end + lag', function () {
    $machine = makeDependencyMachine();
    $pred = makeTask($machine, '2026-01-05 08:00:00', '2026-01-05 12:00:00');
    $succ = makeTask($machine, '2026-01-05 12:00:00', '2026-01-05 14:00:00');
    TaskDependency::create(['predecessor_id' => $pred->id, 'successor_id' => $succ->id, 'lag_minutes' => 30]);

    expect((new DependencyValidator())->checkFs($succ))->toBeFalse();
});

it('detects that closing a chain into a loop would create a cycle', function () {
    $machine = makeDependencyMachine();
    $a = makeTask($machine, '2026-01-05 08:00:00', '2026-01-05 09:00:00');
    $b = makeTask($machine, '2026-01-05 09:00:00', '2026-01-05 10:00:00');
    $c = makeTask($machine, '2026-01-05 10:00:00', '2026-01-05 11:00:00');
    TaskDependency::create(['predecessor_id' => $a->id, 'successor_id' => $b->id, 'lag_minutes' => 0]);
    TaskDependency::create(['predecessor_id' => $b->id, 'successor_id' => $c->id, 'lag_minutes' => 0]);

    $validator = new DependencyValidator();

    // A->B->C már létezik; C-t A elé tenni (C predecessor, A successor) kört zárna: A->B->C->A
    expect($validator->wouldCreateCycle($c->id, $a->id))->toBeTrue();
    // A-t C elé tenni (A predecessor, C successor) nem hoz létre kört, hisz ez az A->B->C irányba illeszkedik
    expect($validator->wouldCreateCycle($a->id, $c->id))->toBeFalse();
});
