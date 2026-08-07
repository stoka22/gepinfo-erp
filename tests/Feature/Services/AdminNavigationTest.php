<?php

use App\Models\Company;
use App\Models\User;
use Filament\Facades\Filament;

it('orders the admin navigation groups with the configured explicit order', function () {
    config(['app.env' => 'local']);

    $company = Company::create(['name' => 'Teszt Kft.']);
    \Spatie\Permission\Models\Role::findOrCreate('admin', 'web');
    $admin = User::factory()->create(['company_id' => $company->id, 'role' => 'admin']);
    $admin->assignRole('admin');

    $this->actingAs($admin)->get('/admin')->assertOk();

    $groups = collect(Filament::getCurrentPanel()->getNavigationGroups())->map->getLabel()->values()->all();

    expect($groups)->toBe(['Hibalisták', 'Rendelések', 'Termelés', 'Kimutatások', 'Készlet', 'Dolgozók', 'Eszközök', 'Importálás', 'Törzsadatok']);
});
