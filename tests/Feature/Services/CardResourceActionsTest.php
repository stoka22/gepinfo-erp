<?php

use App\Filament\Resources\CardResource\Pages\ListCards;
use App\Models\Card;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function cardResourceAdmin(): User
{
    config(['app.env' => 'local']); // ld. TerminalWebhookTest.php: Filament\Http\Middleware\Authenticate

    $company = Company::create(['name' => 'Teszt Kft.']);
    Role::findOrCreate('admin', 'web');

    $admin = User::factory()->create(['company_id' => $company->id, 'role' => 'admin']);
    $admin->assignRole('admin');

    return $admin;
}

it('assigns a card to an employee via the assignToEmployee table action', function () {
    $admin = cardResourceAdmin();
    $this->actingAs($admin);

    $employee = Employee::create(['name' => 'Teszt Dolgozó', 'company_id' => $admin->company_id]);
    $card = Card::create(['uid' => 'CARD-ASSIGN-TEST', 'status' => 'available']);

    Livewire::test(ListCards::class)
        ->callTableAction('assignToEmployee', $card, data: ['employee_id' => $employee->id]);

    expect($card->fresh()->employee_id)->toBe($employee->id);
    expect($card->fresh()->status)->toBe('assigned');
});

it('unassigns a card from its employee via the unassignFromEmployee table action', function () {
    $admin = cardResourceAdmin();
    $this->actingAs($admin);

    $employee = Employee::create(['name' => 'Teszt Dolgozó', 'company_id' => $admin->company_id]);
    $card = Card::create(['uid' => 'CARD-UNASSIGN-TEST', 'status' => 'assigned', 'employee_id' => $employee->id]);

    Livewire::test(ListCards::class)
        ->callTableAction('unassignFromEmployee', $card);

    expect($card->fresh()->employee_id)->toBeNull();
    expect($card->fresh()->status)->toBe('available');
});

it('renders the create-card page for an admin user', function () {
    $admin = cardResourceAdmin();

    $this->actingAs($admin)
        ->get(route('filament.admin.resources.cards.create'))
        ->assertOk();
});

it('exposes a "new card" header action on the cards list page', function () {
    $admin = cardResourceAdmin();
    $this->actingAs($admin);

    Livewire::test(ListCards::class)->assertActionExists('create');
});
