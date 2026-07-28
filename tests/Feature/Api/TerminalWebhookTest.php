<?php

use App\Enums\TimeEntryStatus;
use App\Enums\TimeEntryType;
use App\Models\Card;
use App\Models\Company;
use App\Models\Employee;
use App\Models\TimeEntry;
use App\Models\User;

function terminalEvent(array $overrides = []): array
{
    return array_merge([
        'card_uid'  => 'CARD-1',
        'direction' => 'in',
        'timestamp' => '2026-01-05 08:00:00',
    ], $overrides);
}

beforeEach(function () {
    config(['services.terminal.secret' => 'test-secret']);

    $this->company = Company::create(['name' => 'Test Kft.']);
    $this->admin = User::factory()->create(['company_id' => $this->company->id, 'role' => 'admin']);
    $this->employee = Employee::create([
        'name' => 'Kovács János',
        'company_id' => $this->company->id,
        'account_user_id' => $this->admin->id,
    ]);
    $this->card = Card::create(['uid' => 'CARD-1', 'employee_id' => $this->employee->id, 'status' => 'assigned']);
});

function postTerminalEvent(array $payload, ?string $token = 'test-secret')
{
    return test()->withHeaders($token ? ['X-Auth-Token' => $token] : [])
        ->postJson('/api/terminal/event', $payload);
}

it('rejects requests with a missing or wrong auth token', function () {
    postTerminalEvent(terminalEvent(), null)->assertStatus(401);
    postTerminalEvent(terminalEvent(), 'wrong')->assertStatus(401);
});

it('rejects an unknown card uid', function () {
    postTerminalEvent(terminalEvent(['card_uid' => 'NOPE']))
        ->assertStatus(404)
        ->assertJson(['ok' => false, 'error' => 'unknown_card']);
});

it('creates an open presence entry on check-in', function () {
    postTerminalEvent(terminalEvent())->assertOk()->assertJson(['ok' => true]);

    $entry = TimeEntry::where('employee_id', $this->employee->id)->first();
    expect($entry)->not->toBeNull();
    expect($entry->type)->toBe(TimeEntryType::Presence);
    expect($entry->status)->toBe(TimeEntryStatus::CheckedIn);
    expect($entry->end_time)->toBeNull();
});

it('ignores a duplicate check-in while already checked in', function () {
    postTerminalEvent(terminalEvent())->assertOk();
    postTerminalEvent(terminalEvent(['timestamp' => '2026-01-05 08:05:00']))
        ->assertOk()
        ->assertJson(['ok' => true, 'ignored' => 'already_checked_in']);

    expect(TimeEntry::where('employee_id', $this->employee->id)->count())->toBe(1);
});

it('closes the open entry on check-out and computes hours', function () {
    postTerminalEvent(terminalEvent())->assertOk();
    postTerminalEvent(terminalEvent(['direction' => 'out', 'timestamp' => '2026-01-05 16:30:00']))->assertOk();

    $entry = TimeEntry::where('employee_id', $this->employee->id)->first();
    expect($entry->status)->toBe(TimeEntryStatus::CheckedOut);
    expect((float) $entry->hours)->toBe(8.5);
});

it('rejects a check-out with no open entry', function () {
    postTerminalEvent(terminalEvent(['direction' => 'out']))
        ->assertStatus(409)
        ->assertJson(['ok' => false, 'error' => 'no_open_entry']);
});

it('is idempotent when the same event_id is sent twice', function () {
    postTerminalEvent(terminalEvent(['event_id' => 'evt-1']))->assertOk();
    postTerminalEvent(terminalEvent(['event_id' => 'evt-1', 'timestamp' => '2026-01-05 08:05:00']))
        ->assertOk()
        ->assertJson(['ok' => true, 'duplicate' => true]);

    expect(TimeEntry::where('employee_id', $this->employee->id)->count())->toBe(1);
});

it('stores the location sent on check-in', function () {
    postTerminalEvent(terminalEvent(['location' => 'Gyártócsarnok 1']))->assertOk();

    $entry = TimeEntry::where('employee_id', $this->employee->id)->first();
    expect($entry->location)->toBe('Gyártócsarnok 1');
});

it('still detects a resent check-in event_id as duplicate after the entry has been checked out', function () {
    // A kilépés event_id-ja NEM írhatja felül a belépés event_id-ját a note mezőben,
    // különben egy utólag megismételt belépés-esemény már nem lenne felismerhető duplikátumként.
    postTerminalEvent(terminalEvent(['event_id' => 'evt-in-1']))->assertOk();
    postTerminalEvent(terminalEvent([
        'direction' => 'out',
        'timestamp' => '2026-01-05 16:30:00',
        'event_id'  => 'evt-out-1',
    ]))->assertOk();

    postTerminalEvent(terminalEvent(['event_id' => 'evt-in-1']))
        ->assertOk()
        ->assertJson(['ok' => true, 'duplicate' => true]);

    postTerminalEvent(terminalEvent([
        'direction' => 'out',
        'timestamp' => '2026-01-05 16:30:00',
        'event_id'  => 'evt-out-1',
    ]))
        ->assertOk()
        ->assertJson(['ok' => true, 'duplicate' => true]);

    expect(TimeEntry::where('employee_id', $this->employee->id)->count())->toBe(1);
});

it('converts an explicit UTC timestamp to the application timezone', function () {
    postTerminalEvent(terminalEvent(['timestamp' => '2026-01-05T07:00:00Z']))->assertOk();

    $entry = TimeEntry::where('employee_id', $this->employee->id)->first();
    expect($entry->start_date->toDateString())->toBe('2026-01-05');
    expect($entry->start_time->format('H:i:s'))->toBe('08:00:00'); // Europe/Budapest, téli időszámítás: UTC+1
});
