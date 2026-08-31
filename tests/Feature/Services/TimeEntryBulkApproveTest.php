<?php

use App\Filament\Resources\TimeEntryResource\Pages\ListTimeEntries;
use App\Models\Company;
use App\Models\Employee;
use App\Models\TimeEntry;
use App\Models\User;
use Livewire\Livewire;

it('resolves needs_review on selected presence entries via the bulk "Kijelöltek jóváhagyása" action', function () {
    $company = Company::create(['name' => 'Bulk Jóváhagyás Kft.']);
    \Spatie\Permission\Models\Role::findOrCreate('admin', 'web');
    $admin = User::factory()->create(['company_id' => $company->id, 'role' => 'admin']);
    $admin->assignRole('admin');
    $employee = Employee::create(['name' => 'Tömeges Jóváhagyás Teszt', 'company_id' => $company->id]);

    // Ugyanaz a helyzet, mint amit egy admin valós felhasználáskor kijelölne: egy
    // felülvizsgálatra váró jelenlét-bejegyzés (import-eredetű, egy ambivalens
    // egyetlen kártyaolvasásból), egy jóváhagyásra váró túlóra-kérelem, és egy már
    // rendezett jelenlét-bejegyzés (ezt a tömeges műveletnek NEM szabad módosítania).
    $needsReviewPresence = TimeEntry::forceCreate([
        'employee_id' => $employee->id,
        'company_id' => $company->id,
        'type' => 'presence',
        'status' => 'checked_in',
        'start_date' => '2026-08-14',
        'start_time' => '14:30:00',
        'raw_start_time' => '14:28:45',
        'needs_review' => true,
    ]);

    $pendingOvertime = TimeEntry::forceCreate([
        'employee_id' => $employee->id,
        'company_id' => $company->id,
        'type' => 'overtime',
        'status' => 'pending',
        'start_date' => '2026-08-10',
        'end_date' => '2026-08-10',
        'hours' => 2.0,
    ]);

    $alreadyResolvedPresence = TimeEntry::forceCreate([
        'employee_id' => $employee->id,
        'company_id' => $company->id,
        'type' => 'presence',
        'status' => 'checked_out',
        'start_date' => '2026-08-11',
        'start_time' => '06:00:00',
        'end_date' => '2026-08-11',
        'end_time' => '14:30:00',
        'needs_review' => false,
    ]);

    Livewire::actingAs($admin)
        ->test(ListTimeEntries::class)
        ->callTableBulkAction('approveSelected', [
            $needsReviewPresence, $pendingOvertime, $alreadyResolvedPresence,
        ])
        ->assertNotified();

    expect($needsReviewPresence->fresh()->needs_review)->toBeFalse();
    expect($pendingOvertime->fresh()->status->value)->toBe('approved');
    // Nem szabad megváltoznia -- nem volt sem felülvizsgálandó, sem pending.
    expect($alreadyResolvedPresence->fresh()->needs_review)->toBeFalse();
    expect($alreadyResolvedPresence->fresh()->status->value)->toBe('checked_out');
});
