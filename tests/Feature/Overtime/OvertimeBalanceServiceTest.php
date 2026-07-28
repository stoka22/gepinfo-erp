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
