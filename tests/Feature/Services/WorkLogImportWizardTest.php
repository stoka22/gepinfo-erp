<?php

use App\Filament\Resources\WorkLogResource\Pages\ListWorkLogs;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkLog;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

it('imports via the admin wizard action: auto-matches known names and applies manual employee assignments for unknown ones', function () {
    $company = Company::create(['name' => 'Wizard Kft.']);
    $admin = User::factory()->create(['company_id' => $company->id, 'role' => 'admin']);
    $matched = Employee::create(['name' => 'Ismert Dolgozó', 'company_id' => $company->id]);
    $manuallyAssigned = Employee::create(['name' => 'Teljesen Más Név', 'company_id' => $company->id]);

    $this->actingAs($admin);

    $html = '<html><body><table>'
        . '<tr><td>Nev</td><td>Munkakor</td><td>Helyiseg</td><td>Belepesi</td><td>Kezdes</td><td>Kilepesi</td><td>Vege</td><td>Ido</td></tr>'
        . '<tr><td>Ismert Dolgozó</td><td>Op</td><td>Uzem</td><td>K1</td><td>2026. jan. 5. 08:00:00</td><td>K1</td><td>2026. jan. 5. 16:00:00</td><td>08:00</td></tr>'
        . '<tr><td>Ismeretlen Dolgozó</td><td>Op</td><td>Uzem</td><td>K2</td><td>2026. jan. 6. 08:00:00</td><td>K2</td><td>2026. jan. 6. 16:00:00</td><td>08:00</td></tr>'
        . '</table></body></html>';

    $uploadedFile = UploadedFile::fake()->createWithContent('import.xls', $html);

    $assignKey = 'assign_' . md5('Ismeretlen Dolgozó');

    Livewire::test(ListWorkLogs::class)
        ->mountTableAction('import')
        ->setTableActionData(['file' => $uploadedFile])
        ->setTableActionData([$assignKey => $manuallyAssigned->id])
        ->callMountedTableAction();

    $matchedRow = WorkLog::where('nev', 'Ismert Dolgozó')->first();
    $unknownRow = WorkLog::where('nev', 'Ismeretlen Dolgozó')->first();

    expect($matchedRow)->not->toBeNull();
    expect($matchedRow->employee_id)->toBe($matched->id);

    expect($unknownRow)->not->toBeNull();
    expect($unknownRow->employee_id)->toBe($manuallyAssigned->id);
});
