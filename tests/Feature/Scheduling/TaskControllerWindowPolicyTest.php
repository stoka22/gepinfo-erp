<?php

use App\Models\Company;
use App\Models\Machine;
use App\Models\MachineBlocking;
use App\Models\ResourceShiftAssignment;
use App\Models\ShiftPattern;

function windowPolicyMachine(): Machine
{
    $company = Company::create(['name' => 'Test Kft.']);

    return Machine::create(['name' => 'CNC-1', 'code' => 'GEP-' . uniqid(), 'company_id' => $company->id]);
}

it('allows scheduling a task on a machine with no shift assignment configured (backward compatible)', function () {
    $machine = windowPolicyMachine();

    $response = $this->postJson('/api/scheduler/tasks', [
        'machine_id' => $machine->id,
        'start' => '2026-01-05 20:00:00', // hétfő este, semmilyen ablak nincs beállítva
        'end' => '2026-01-05 22:00:00',
        'ratePph' => 10,
    ]);

    $response->assertOk()->assertJson(['ok' => true]);
});

it('rejects a task placed outside the machine shift window', function () {
    $machine = windowPolicyMachine();
    $pattern = ShiftPattern::create([
        'name' => 'Nappal',
        'days_mask' => 0b0011111, // hétfő-péntek (H=bit0 .. V=bit6)
        'start_time' => '08:00:00',
        'end_time' => '16:00:00',
    ]);
    ResourceShiftAssignment::create([
        'resource_id' => $machine->id,
        'shift_pattern_id' => $pattern->id,
        'valid_from' => '2020-01-01',
        'valid_to' => null,
    ]);

    // 2026-01-05 hétfő; 20:00-22:00 a 08-16 műszakon kívül esik
    $response = $this->postJson('/api/scheduler/tasks', [
        'machine_id' => $machine->id,
        'start' => '2026-01-05 20:00:00',
        'end' => '2026-01-05 22:00:00',
        'ratePph' => 10,
    ]);

    $response->assertStatus(422);
});

it('allows a task placed inside the configured shift window', function () {
    $machine = windowPolicyMachine();
    $pattern = ShiftPattern::create([
        'name' => 'Nappal',
        'days_mask' => 0b0011111,
        'start_time' => '08:00:00',
        'end_time' => '16:00:00',
    ]);
    ResourceShiftAssignment::create([
        'resource_id' => $machine->id,
        'shift_pattern_id' => $pattern->id,
        'valid_from' => '2020-01-01',
        'valid_to' => null,
    ]);

    $response = $this->postJson('/api/scheduler/tasks', [
        'machine_id' => $machine->id,
        'start' => '2026-01-05 09:00:00',
        'end' => '2026-01-05 11:00:00',
        'ratePph' => 10,
    ]);

    $response->assertOk()->assertJson(['ok' => true]);
});

it('rejects a task overlapping a machine blocking period', function () {
    $machine = windowPolicyMachine();
    MachineBlocking::create([
        'machine_id' => $machine->id,
        'starts_at' => '2026-01-05 09:00:00',
        'ends_at' => '2026-01-05 12:00:00',
        'reason' => 'karbantartás',
    ]);

    $response = $this->postJson('/api/scheduler/tasks', [
        'machine_id' => $machine->id,
        'start' => '2026-01-05 10:00:00',
        'end' => '2026-01-05 11:00:00',
        'ratePph' => 10,
    ]);

    $response->assertStatus(422);
});

it('returns the shift window for a configured weekday and 404 for an unconfigured one', function () {
    ShiftPattern::create([
        'name' => 'Nappal',
        'days_mask' => 0b0011111, // hétfő-péntek (H=bit0 .. V=bit6)
        'start_time' => '08:00:00',
        'end_time' => '16:00:00',
    ]);

    // 2026-01-05 hétfő -> van műszak
    $monday = $this->getJson('/api/scheduler/shift-window?date=2026-01-05');
    $monday->assertOk()->assertJson(['start' => '08:00:00', 'end' => '16:00:00']);

    // 2026-01-04 vasárnap -> nincs műszak
    $sunday = $this->getJson('/api/scheduler/shift-window?date=2026-01-04');
    $sunday->assertStatus(404);
});
