<?php

use App\Filament\Resources\EmployeeResource\Pages\ListEmployees;
use App\Jobs\GenerateAttendanceSheetBatchJob;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
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

it('dispatches the summary and detailed bulk actions as a background batch job, not a synchronous download', function () {
    // Korábban ez a két bulk action szinkron StreamedResponse-t épített -- ezt később
    // háttér-jobra (GenerateAttendanceSheetBatchJob) cserélték, mert élesben 10 dolgozó
    // fölött a szinkron Dompdf-renderelés túllépte a webszerver/PHP időtúllépését (lásd a
    // job osztály doc-kommentjét). A teszt ezt a jelenlegi, szándékos viselkedést
    // ellenőrzi: az action jobot dispatchol és nem ad vissza semmilyen HTTP választ.
    config(['app.env' => 'local']);
    Queue::fake();

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

        expect($response)->toBeNull();
    }

    Queue::assertPushed(GenerateAttendanceSheetBatchJob::class, 2);
});

it('processes multiple employees in one merged batch job dispatch', function () {
    // Éles hiba (korábbi, szinkron megoldásnál): sok dolgozó egyszerre kiválasztva (pl. a
    // teljes cég) az alapértelmezett 128M PHP memória-limitet a Dompdf renderelés
    // túllépte ("Allowed memory size exhausted" fatal error, mérve: ~52 dolgozónál) --
    // emiatt lett a renderelés háttér-jobra (GenerateAttendanceSheetBatchJob) kiszervezve,
    // ami saját maga emeli a memória-limitet a futása alatt. Itt azt ellenőrizzük, hogy
    // az action egyetlen jobot dispatchol az összes kijelölt dolgozó ID-jével.
    config(['app.env' => 'local']);
    Queue::fake();

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

    expect($response)->toBeNull();
    Queue::assertPushed(GenerateAttendanceSheetBatchJob::class, function ($job) use ($employees) {
        $ids = (new ReflectionProperty($job, 'employeeIds'))->getValue($job);
        sort($ids);

        return $ids === $employees->pluck('id')->sort()->values()->all();
    });
});
