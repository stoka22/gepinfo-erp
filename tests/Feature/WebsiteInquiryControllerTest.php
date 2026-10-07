<?php

use App\Models\WebsiteInquiry;

it('renders the public questionnaire form', function () {
    $this->get(route('weboldal-igenyfelmero'))
        ->assertOk()
        ->assertSee('Weboldal igényfelmérő kérdőív')
        ->assertSee('Cégnév');
});

it('stores a submitted questionnaire and redirects with a success flash', function () {
    $response = $this->post(route('weboldal-igenyfelmero.store'), [
        'cegnev' => 'Teszt Kft.',
        'kapcsolattarto_neve' => 'Kovács János',
        'telefon' => '+36201234567',
        'email' => 'janos@teszt.hu',
        'van_weboldal' => 'nincs_elso',
        'weboldal_celja' => ['google_terkep', 'ajanlatkeres_online'],
        'fo_vasarlok' => ['maganszemelyek'],
        'aloldalak' => ['fooldal', 'kapcsolat_terkep'],
        'koltsegkeret' => 'ajanlatot_kerek',
    ]);

    $response->assertRedirect(route('weboldal-igenyfelmero'));
    $response->assertSessionHas('success', true);

    $inquiry = WebsiteInquiry::first();
    expect($inquiry)->not->toBeNull();
    expect($inquiry->cegnev)->toBe('Teszt Kft.');
    expect($inquiry->kapcsolattarto_neve)->toBe('Kovács János');
    expect($inquiry->telefon)->toBe('+36201234567');
    expect($inquiry->answers['van_weboldal'])->toBe('nincs_elso');
    expect($inquiry->answers['weboldal_celja'])->toBe(['google_terkep', 'ajanlatkeres_online']);
    expect($inquiry->answers)->not->toHaveKey('cegnev');
});

it('requires a company name', function () {
    $response = $this->post(route('weboldal-igenyfelmero.store'), [
        'telefon' => '+36201234567',
    ]);

    $response->assertSessionHasErrors('cegnev');
    expect(WebsiteInquiry::count())->toBe(0);
});

it('requires at least a phone number or email address', function () {
    $response = $this->post(route('weboldal-igenyfelmero.store'), [
        'cegnev' => 'Teszt Kft.',
    ]);

    $response->assertSessionHasErrors('telefon');
    expect(WebsiteInquiry::count())->toBe(0);
});

it('silently discards submissions where the honeypot field was filled', function () {
    $response = $this->post(route('weboldal-igenyfelmero.store'), [
        'cegnev' => 'Bot Kft.',
        'telefon' => '123',
        'website' => 'http://spam.example',
    ]);

    $response->assertRedirect(route('weboldal-igenyfelmero'));
    expect(WebsiteInquiry::count())->toBe(0);
});
