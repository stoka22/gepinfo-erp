<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\OvertimeBalance;
use App\Models\User;

function overtimeBalanceResourceAdmin(): User
{
    config(['app.env' => 'local']);

    $company = Company::create(['name' => 'Riport Kft.']);
    \Spatie\Permission\Models\Role::findOrCreate('admin', 'web');
    $admin = User::factory()->create(['company_id' => $company->id, 'role' => 'admin']);
    $admin->assignRole('admin');

    return $admin;
}

it('lists overtime balances sorted with the largest deficit first', function () {
    $admin = overtimeBalanceResourceAdmin();
    $company = $admin->company;

    $deficit = Employee::create(['name' => 'Deficit Dolgozó', 'company_id' => $company->id]);
    $surplus = Employee::create(['name' => 'Túlórás Dolgozó', 'company_id' => $company->id]);

    OvertimeBalance::create(['employee_id' => $deficit->id, 'company_id' => $company->id, 'balance_minutes' => -200, 'manual_adjustment_minutes' => 0]);
    OvertimeBalance::create(['employee_id' => $surplus->id, 'company_id' => $company->id, 'balance_minutes' => 150, 'manual_adjustment_minutes' => 0]);

    $response = $this->actingAs($admin)->get('/admin/overtime-balances');

    $response->assertOk();
    $response->assertSee('Deficit Dolgozó');
    $response->assertSee('Túlórás Dolgozó');
});

it('filters overtime balances to only deficit rows', function () {
    $admin = overtimeBalanceResourceAdmin();
    $company = $admin->company;

    $deficit = Employee::create(['name' => 'Deficit Szűrő', 'company_id' => $company->id]);
    $surplus = Employee::create(['name' => 'Túlóra Szűrő', 'company_id' => $company->id]);

    $deficitBalance = OvertimeBalance::create(['employee_id' => $deficit->id, 'company_id' => $company->id, 'balance_minutes' => -50, 'manual_adjustment_minutes' => 0]);
    $surplusBalance = OvertimeBalance::create(['employee_id' => $surplus->id, 'company_id' => $company->id, 'balance_minutes' => 50, 'manual_adjustment_minutes' => 0]);

    Livewire\Livewire::test(\App\Filament\Resources\OvertimeBalanceResource\Pages\ListOvertimeBalances::class)
        ->filterTable('deficit')
        ->assertCanSeeTableRecords([$deficitBalance])
        ->assertCanNotSeeTableRecords([$surplusBalance]);
});
