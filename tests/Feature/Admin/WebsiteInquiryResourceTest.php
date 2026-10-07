<?php

use App\Models\Company;
use App\Models\User;
use App\Models\WebsiteInquiry;

beforeEach(function () {
    $this->company = Company::create(['name' => 'Test Kft.']);
    $this->admin = User::factory()->create(['company_id' => $this->company->id, 'role' => 'admin']);

    config(['app.env' => 'local']);
    $this->admin->assignRole(\Spatie\Permission\Models\Role::findOrCreate('admin', 'web'));
});

it('renders the admin website-inquiries list page for an admin user', function () {
    WebsiteInquiry::create([
        'cegnev' => 'Acme Kft.',
        'kapcsolattarto_neve' => 'Teszt Elek',
        'telefon' => '+36201234567',
        'email' => 'elek@acme.hu',
        'answers' => ['van_weboldal' => 'nincs_elso'],
    ]);

    $this->actingAs($this->admin)
        ->get(route('filament.admin.resources.website-inquiries.index'))
        ->assertOk()
        ->assertSee('Acme Kft.')
        ->assertSee('Teszt Elek');
});

it('renders the admin website-inquiry detail page with labeled answers', function () {
    $inquiry = WebsiteInquiry::create([
        'cegnev' => 'Acme Kft.',
        'kapcsolattarto_neve' => 'Teszt Elek',
        'telefon' => '+36201234567',
        'email' => 'elek@acme.hu',
        'answers' => [
            'van_weboldal' => 'nincs_elso',
            'weboldal_celja' => ['google_terkep', 'ajanlatkeres_online'],
        ],
    ]);

    $this->actingAs($this->admin)
        ->get(route('filament.admin.resources.website-inquiries.view', $inquiry))
        ->assertOk()
        ->assertSee('Nincs, ez lesz az első')
        ->assertSee('Megtaláljanak a Google-ben és a térképen')
        ->assertSee('Ajánlatkérések fogadása online');
});
