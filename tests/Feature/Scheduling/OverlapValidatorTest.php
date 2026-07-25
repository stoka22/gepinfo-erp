<?php

use App\Models\Company;
use App\Models\Machine;
use App\Models\Task;
use App\Services\Scheduling\OverlapValidator;

function overlapTestMachine(string $code): Machine
{
    $company = Company::create(['name' => 'Test Kft.']);

    return Machine::create(['name' => 'CNC-'.$code, 'code' => 'GEP-'.uniqid(), 'company_id' => $company->id]);
}

it('detects an overlapping task on the same machine', function () {
    $machine = overlapTestMachine('1');
    Task::create([
        'name' => 'Existing',
        'machine_id' => $machine->id,
        'starts_at' => '2026-01-05 09:00:00',
        'ends_at' => '2026-01-05 12:00:00',
    ]);
    $validator = new OverlapValidator();

    expect($validator->hasOverlap($machine->id, '2026-01-05 10:00:00', '2026-01-05 11:00:00'))->toBeTrue();
});

it('reports no overlap for a non-overlapping window', function () {
    $machine = overlapTestMachine('1');
    Task::create([
        'name' => 'Existing',
        'machine_id' => $machine->id,
        'starts_at' => '2026-01-05 09:00:00',
        'ends_at' => '2026-01-05 12:00:00',
    ]);
    $validator = new OverlapValidator();

    expect($validator->hasOverlap($machine->id, '2026-01-05 13:00:00', '2026-01-05 14:00:00'))->toBeFalse();
});

it('ignores no overlap on a different machine', function () {
    $machineA = overlapTestMachine('1');
    $machineB = overlapTestMachine('2');
    Task::create([
        'name' => 'Existing',
        'machine_id' => $machineA->id,
        'starts_at' => '2026-01-05 09:00:00',
        'ends_at' => '2026-01-05 12:00:00',
    ]);
    $validator = new OverlapValidator();

    expect($validator->hasOverlap($machineB->id, '2026-01-05 09:00:00', '2026-01-05 12:00:00'))->toBeFalse();
});

it('excludes the ignored task id from the overlap check', function () {
    $machine = overlapTestMachine('1');
    $task = Task::create([
        'name' => 'Existing',
        'machine_id' => $machine->id,
        'starts_at' => '2026-01-05 09:00:00',
        'ends_at' => '2026-01-05 12:00:00',
    ]);
    $validator = new OverlapValidator();

    expect($validator->hasOverlap($machine->id, '2026-01-05 09:00:00', '2026-01-05 12:00:00', $task->id))->toBeFalse();
});
