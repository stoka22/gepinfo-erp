<?php

use App\Filament\Resources\EmployeeResource\Pages\ListEmployees;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Livewire\Livewire;

it('opens the self-service attendance sheet inline instead of forcing a download', function () {
    $company = Company::create(['name' => 'Inline Kft.']);
    $user = User::factory()->create(['company_id' => $company->id]);
    $employee = Employee::create(['name' => 'Inline Teszt', 'company_id' => $company->id, 'account_user_id' => $user->id]);

    $response = $this->actingAs($user)->get(route('my-attendance-sheet', ['monthsAgo' => 0]));

    $response->assertOk();
    $disposition = $response->headers->get('Content-Disposition');
    expect($disposition)->toContain('inline');
    expect($disposition)->not->toContain('attachment');
});

it('opens the self-service detailed attendance sheet inline too', function () {
    $company = Company::create(['name' => 'Inline Kft. 2']);
    $user = User::factory()->create(['company_id' => $company->id]);
    $employee = Employee::create(['name' => 'Inline Teszt 2', 'company_id' => $company->id, 'account_user_id' => $user->id]);

    $response = $this->actingAs($user)->get(route('my-attendance-sheet-detailed', ['monthsAgo' => 0]));

    $response->assertOk();
    $disposition = $response->headers->get('Content-Disposition');
    expect($disposition)->toContain('inline');
    expect($disposition)->not->toContain('attachment');
});

it('exposes both the summary and the detailed attendance-sheet bulk actions on the employee list', function () {
    config(['app.env' => 'local']);

    $company = Company::create(['name' => 'Inline Admin Kft.']);
    $admin = User::factory()->create(['company_id' => $company->id, 'role' => 'admin']);
    Employee::create(['name' => 'Inline Admin Teszt', 'company_id' => $company->id]);

    $this->actingAs($admin);

    Livewire::test(ListEmployees::class)
        ->assertTableBulkActionExists('attendance_sheet')
        ->assertTableBulkActionExists('attendance_sheet_detailed');
});

it('builds the summary and detailed bulk actions with an inline (not attachment) Content-Disposition', function () {
    // A két bulk action ugyanazt a StreamedResponse-építő zárt függvényt hívja meg — ezt itt
    // közvetlenül, Livewire nélkül futtatjuk, mert a Livewire teszt-API nem teszi könnyen
    // elérhetővé egy bulk action StreamedResponse visszatérési értékét.
    config(['app.env' => 'local']);

    $company = Company::create(['name' => 'Inline Direct Kft.']);
    $admin = User::factory()->create(['company_id' => $company->id, 'role' => 'admin']);
    $employee = Employee::create(['name' => 'Inline Direct Teszt', 'company_id' => $company->id]);

    $this->actingAs($admin);

    $ref = new ReflectionMethod(\App\Filament\Resources\EmployeeResource\Tables\EmployeeTable::class, 'attendanceSheetBulkAction');
    $ref->setAccessible(true);

    foreach (['exports.attendance-sheet' => 'attendance_sheet', 'exports.attendance-sheet-detailed' => 'attendance_sheet_detailed'] as $view => $name) {
        $bulkAction = $ref->invoke(null, $name, 'Teszt', $view, 'jelenleti_iv_teszt');
        $actionClosure = $bulkAction->getActionFunction();

        $response = $actionClosure(collect([$employee]), ['year' => now()->year, 'months' => [now()->format('m')]]);

        expect($response)->toBeInstanceOf(\Symfony\Component\HttpFoundation\StreamedResponse::class);
        $disposition = $response->headers->get('Content-Disposition');
        expect($disposition)->toContain('inline');
        expect($disposition)->not->toContain('attachment');
    }
});

it('processes multiple employees in one merged export and raises the memory limit for the render', function () {
    // Éles hiba: sok dolgozó egyszerre kiválasztva (pl. a teljes cég) az alapértelmezett
    // 128M PHP memória-limitet a Dompdf renderelés túllépte ("Allowed memory size
    // exhausted" fatal error, mérve: ~52 dolgozónál) -- a bulk action mostantól 512M-re
    // emeli a limitet a renderelés idejére. Itt csak azt ellenőrizzük, hogy az action
    // ténylegesen lefuttatja ezt az emelést (nem szimulálunk valódi memóriatúllépést,
    // mert az elfuttatott teszt-folyamat maga is a limithez közeli memóriát használ).
    config(['app.env' => 'local']);

    $company = Company::create(['name' => 'Inline Multi Kft.']);
    $admin = User::factory()->create(['company_id' => $company->id, 'role' => 'admin']);
    $employees = collect([
        Employee::create(['name' => 'Több Dolgozó Egy', 'company_id' => $company->id]),
        Employee::create(['name' => 'Több Dolgozó Kettő', 'company_id' => $company->id]),
        Employee::create(['name' => 'Több Dolgozó Három', 'company_id' => $company->id]),
    ]);

    $this->actingAs($admin);

    $ref = new ReflectionMethod(\App\Filament\Resources\EmployeeResource\Tables\EmployeeTable::class, 'attendanceSheetBulkAction');
    $ref->setAccessible(true);
    $bulkAction = $ref->invoke(null, 'attendance_sheet', 'Teszt', 'exports.attendance-sheet', 'jelenleti_iv_teszt');
    $actionClosure = $bulkAction->getActionFunction();

    $response = $actionClosure($employees, ['year' => now()->year, 'months' => [now()->format('m')]]);

    expect($response)->toBeInstanceOf(\Symfony\Component\HttpFoundation\StreamedResponse::class);
    expect(ini_get('memory_limit'))->toBe('512M');
});
