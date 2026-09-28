<?php

use App\Enums\TimeEntryStatus;
use App\Enums\TimeEntryType;
use App\Filament\Resources\TimeEntryResource;
use App\Filament\Resources\TimeEntryResource\Pages\EditTimeEntry;
use App\Models\Company;
use App\Models\Employee;
use App\Models\TimeEntry;

it('redirects back to the exact filtered/paginated list URL after saving, but only when it actually came from that list', function () {
    $company = Company::create(['name' => 'Vissza Navigálás Kft.']);
    $employee = Employee::create(['name' => 'Vissza Navigálás Teszt', 'company_id' => $company->id]);
    $entry = TimeEntry::create([
        'employee_id' => $employee->id, 'company_id' => $company->id,
        'type' => TimeEntryType::Presence->value, 'status' => TimeEntryStatus::CheckedOut->value,
        'start_date' => '2026-07-07', 'start_time' => '05:55:00',
        'end_date' => '2026-07-07', 'end_time' => '14:48:00',
    ]);

    $page = new EditTimeEntry();
    $page->record = $entry;

    $method = new ReflectionMethod($page, 'getRedirectUrl');
    $indexUrl = TimeEntryResource::getUrl('index');

    // A lista URL-je aktív szűrőkkel és lapszámmal -- pontosan erre kell visszatérnie.
    $listUrlWithFilters = $indexUrl.'?tableFilters%5Btypes_visible%5D%5Btypes%5D%5B0%5D=presence&page=3';
    $page->previousUrl = $listUrlWithFilters;
    expect($method->invoke($page))->toBe($listUrlWithFilters);

    // Ha a "previousUrl" nem a lista oldalára mutat (pl. egy külső/ismeretlen URL), a sima
    // (szűrő/lapszám nélküli) index URL a biztonságos visszaesés.
    $page->previousUrl = 'https://evil.example.com/whatever';
    expect($method->invoke($page))->toBe($indexUrl);

    // Ha nincs egyáltalán previousUrl (pl. közvetlen linkről nyitották meg a szerkesztést).
    $page->previousUrl = null;
    expect($method->invoke($page))->toBe($indexUrl);
});
