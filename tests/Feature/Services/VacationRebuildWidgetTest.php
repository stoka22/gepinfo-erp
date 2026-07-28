<?php

use App\Filament\Widgets\VacationRebuildWidget;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Models\VacationBalance;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function vacationWidgetAdmin(): User
{
    $company = Company::create(['name' => 'Teszt Kft.']);
    Employee::create(['name' => 'Teszt Dolgozó', 'company_id' => $company->id]);

    Role::findOrCreate('admin', 'web');

    $admin = User::factory()->create(['company_id' => $company->id, 'role' => 'admin']);
    $admin->assignRole('admin');

    return $admin;
}

it('is only visible to admins', function () {
    $company = Company::create(['name' => 'Teszt Kft.']);
    $user = User::factory()->create(['company_id' => $company->id, 'role' => 'user']);

    $this->actingAs($user);
    expect(VacationRebuildWidget::canView())->toBeFalse();

    $admin = vacationWidgetAdmin();
    $this->actingAs($admin);
    expect(VacationRebuildWidget::canView())->toBeTrue();
});

it('rebuilds vacation balances for the given year via the widget action', function () {
    $admin = vacationWidgetAdmin();
    $this->actingAs($admin);

    expect(VacationBalance::where('year', 2027)->count())->toBe(0);

    Livewire::test(VacationRebuildWidget::class)
        ->callAction('rebuildVacation', ['year' => 2027])
        ->assertNotified();

    expect(VacationBalance::where('year', 2027)->count())->toBe(1);
});
