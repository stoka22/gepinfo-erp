<?php

namespace App\Console\Commands;

use App\Enums\TimeEntryStatus;
use App\Enums\TimeEntryType;
use App\Models\Employee;
use App\Models\OvertimeBalance;
use App\Models\TimeEntry;
use App\Models\VacationBalance;
use App\Services\Calendar\WorkdayResolver;
use App\Services\Overtime\OvertimeBalanceService;
use App\Support\TimeRounding;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ImportDailyAttendance extends Command
{
    private WorkdayResolver $workdayResolver;

    protected $signature = 'import:daily-attendance
        {file : Napi bontású beléptető export (.xls/.xlsx) elérési útja}
        {--employee= : Dolgozó ID kényszerítése, ha a névegyeztetés nem megy}
        {--tz=Europe/Budapest}
        {--dry : Csak szimuláció, nincs írás}';

    protected $description = 'Egy dolgozóra szóló, napi bontású beléptető export (Nap|Kezdés|Vége|Munkaidő) importja a time_entries táblába. '
        .'A munkakezdés fél órára felfelé kerekítve; 8:30 felett túlóra. Munkanapi hiányzásnál elsőként a túlóra-keret, '
        .'majd a szabadság terhelődik (jóváhagyásra várva), fedezet híján "Igazolatlan távollét" kerül rögzítésre.';

    public function handle(WorkdayResolver $workdayResolver): int
    {
        $this->workdayResolver = $workdayResolver;

        $file = $this->argument('file');
        $tz   = $this->option('tz') ?: 'Europe/Budapest';
        $dry  = (bool) $this->option('dry');
        $batchId = 'daily_'.date('Ymd_His').'_'.Str::random(6);

        if (! is_file($file)) {
            $this->error("Nincs fájl: $file");
            return self::FAILURE;
        }

        /** @var Worksheet $sheet */
        $sheet = IOFactory::load($file)->getActiveSheet();
        $highestRow = (int) $sheet->getHighestRow();

        if ($highestRow < 3) {
            $this->warn('Üres fájl.');
            return self::SUCCESS;
        }

        $headerName = trim((string) $sheet->getCell('A1')->getValue());

        $employee = $this->resolveEmployee($headerName);
        if (! $employee) {
            return self::FAILURE;
        }

        $this->info("Dolgozó: {$employee->name} (ID {$employee->id})");

        $ob = OvertimeBalance::where('employee_id', $employee->id)->first();
        $overtimeMinutesAvailable = $ob ? ($ob->balance_minutes + $ob->manual_adjustment_minutes) : 0;

        $vacationRemainingByYear = [];
        $getVacationRemaining = function (int $year) use (&$vacationRemainingByYear, $employee): float {
            if (! array_key_exists($year, $vacationRemainingByYear)) {
                $vb = VacationBalance::where('employee_id', $employee->id)->where('year', $year)->first();
                $vacationRemainingByYear[$year] = $vb ? $vb->remaining_days : 0.0;
            }
            return $vacationRemainingByYear[$year];
        };

        $inserted = 0;
        $skippedDayOff = 0;
        $skippedExisting = 0;
        $flaggedReview = 0;
        $absenceOvertimeCount = 0;
        $absenceVacationCount = 0;
        $absenceUncoveredCount = 0;

        for ($r = 3; $r <= $highestRow; $r++) {
            $dateRaw  = trim((string) $sheet->getCell("A{$r}")->getValue());
            $startRaw = trim((string) $sheet->getCell("C{$r}")->getValue());
            $endRaw   = trim((string) $sheet->getCell("D{$r}")->getValue());

            if ($dateRaw === '') {
                continue;
            }

            try {
                $date = CarbonImmutable::parse($dateRaw, $tz);
            } catch (\Throwable) {
                $this->warn("Sor #{$r}: érvénytelen dátum '{$dateRaw}', kihagyva.");
                continue;
            }

            // Nincs be- és kilépés sem: munkanapi hiányzás vagy tényleges pihenőnap.
            if ($startRaw === '' && $endRaw === '') {
                if (! $this->isWorkingDay($employee, $date)) {
                    $skippedDayOff++;
                    continue;
                }

                $existsAbsence = TimeEntry::query()
                    ->where('employee_id', $employee->id)
                    ->where('entry_method', 'daily-import-absence')
                    ->whereDate('start_date', $date->toDateString())
                    ->exists();

                if ($existsAbsence) {
                    $skippedExisting++;
                    continue;
                }

                if ($overtimeMinutesAvailable >= OvertimeBalanceService::STANDARD_WORKDAY_MINUTES) {
                    if (! $dry) {
                        TimeEntry::create([
                            'employee_id'  => $employee->id,
                            'type'         => TimeEntryType::Overtime->value,
                            'status'       => TimeEntryStatus::Pending->value,
                            'start_date'   => $date->toDateString(),
                            'end_date'     => $date->toDateString(),
                            'hours'        => -8.5,
                            'entry_method' => 'daily-import-absence',
                            'note'         => 'batch='.$batchId.';name='.$headerName.';absence=overtime-covered',
                        ]);
                    }
                    $overtimeMinutesAvailable -= OvertimeBalanceService::STANDARD_WORKDAY_MINUTES;
                    $absenceOvertimeCount++;
                } elseif ($getVacationRemaining((int) $date->year) >= 1.0) {
                    if (! $dry) {
                        TimeEntry::create([
                            'employee_id'  => $employee->id,
                            'type'         => TimeEntryType::Vacation->value,
                            'status'       => TimeEntryStatus::Pending->value,
                            'start_date'   => $date->toDateString(),
                            'end_date'     => $date->toDateString(),
                            'entry_method' => 'daily-import-absence',
                            'note'         => 'batch='.$batchId.';name='.$headerName.';absence=vacation-covered',
                        ]);
                    }
                    $vacationRemainingByYear[(int) $date->year] -= 1.0;
                    $absenceVacationCount++;
                } else {
                    if (! $dry) {
                        TimeEntry::create([
                            'employee_id'  => $employee->id,
                            'type'         => TimeEntryType::UnauthorizedAbsence->value,
                            'status'       => TimeEntryStatus::Pending->value,
                            'start_date'   => $date->toDateString(),
                            'end_date'     => $date->toDateString(),
                            'entry_method' => 'daily-import-absence',
                            'needs_review' => true,
                            'note'         => 'batch='.$batchId.';name='.$headerName.';absence=uncovered-needs-classification',
                        ]);
                    }
                    $absenceUncoveredCount++;
                }
                continue;
            }

            $exists = TimeEntry::query()
                ->where('employee_id', $employee->id)
                ->where('type', TimeEntryType::Presence->value)
                ->where('entry_method', 'daily-import')
                ->whereDate('start_date', $date->toDateString())
                ->exists();

            if ($exists) {
                $skippedExisting++;
                continue;
            }

            $needsReview = ($startRaw === '') !== ($endRaw === ''); // pontosan az egyik hiányzik

            $rawStartTime = $startRaw !== '' ? $startRaw.':00' : null;
            $startTime = $startRaw !== '' ? TimeRounding::roundStartUpToHalfHour($startRaw).':00' : null;
            $endTime   = $endRaw !== ''   ? $endRaw.':00'   : null;

            $workedMinutes = null;
            $hours = null;
            if ($startTime && $endTime) {
                $start = $date->setTimeFromTimeString($startTime);
                $end   = $date->setTimeFromTimeString($endTime);
                if ($end->lte($start)) {
                    $needsReview = true;
                } else {
                    $workedMinutes = abs($end->diffInMinutes($start));
                    $hours = round($workedMinutes / 60, 2);
                }
            }

            if ($needsReview) {
                $flaggedReview++;
            }

            if (! $dry) {
                TimeEntry::create([
                    'employee_id'    => $employee->id,
                    'type'           => TimeEntryType::Presence->value,
                    'status'         => $endTime ? TimeEntryStatus::CheckedOut->value : TimeEntryStatus::CheckedIn->value,
                    'start_date'     => $date->toDateString(),
                    'start_time'     => $startTime,
                    'raw_start_time' => $rawStartTime,
                    'end_date'       => $endTime ? $date->toDateString() : null,
                    'end_time'       => $endTime,
                    'worked_minutes' => $workedMinutes,
                    'hours'          => $hours,
                    'entry_method'   => 'daily-import',
                    'needs_review'   => $needsReview,
                    'note'           => 'batch='.$batchId.';name='.$headerName,
                ]);
            }

            $inserted++;
        }

        $this->info("Beszúrva (jelenlét): {$inserted} (ebből felülvizsgálandó: {$flaggedReview})");
        $this->info("Hiányzás besorolva - túlóra terhére: {$absenceOvertimeCount}, szabadság terhére: {$absenceVacationCount}, "
            ."nincs fedezet (igazolatlan/táppénz, ellenőrizendő): {$absenceUncoveredCount}");
        $this->line("Kihagyva - pihenőnap: {$skippedDayOff}, már létező: {$skippedExisting}");

        if ($dry) {
            $this->warn('Dry-run, nincs írás.');
            return self::SUCCESS;
        }

        $this->info("Kész. Batch: {$batchId}");
        $this->line("Visszavonás: DELETE FROM time_entries WHERE note LIKE '%batch={$batchId}%';");

        return self::SUCCESS;
    }

    private function isWorkingDay(Employee $employee, CarbonImmutable $date): bool
    {
        return $this->workdayResolver->isWorkingDayForEmployee($employee, $date);
    }

    private function resolveEmployee(string $headerName): ?Employee
    {
        if ($id = $this->option('employee')) {
            $employee = Employee::find((int) $id);
            if (! $employee) {
                $this->error("Nincs ilyen dolgozó ID: {$id}");
            }
            return $employee;
        }

        if ($headerName === '') {
            $this->error('Nem található dolgozó név a fájl fejlécében (A1 cella), add meg --employee=ID -val.');
            return null;
        }

        $norm = $this->normalizeName($headerName);
        $matches = Employee::all(['id', 'name'])
            ->filter(fn (Employee $e) => $this->normalizeName($e->name) === $norm);

        if ($matches->count() === 1) {
            return $matches->first();
        }

        if ($matches->count() > 1) {
            $this->error("Több dolgozó is illeszkedik a névre ('{$headerName}'): ".$matches->pluck('id')->implode(', ').". Add meg --employee=ID -val.");
            return null;
        }

        $this->error("Nem található dolgozó a névre: '{$headerName}'. Add meg --employee=ID -val.");
        return null;
    }

    private function normalizeName(string $name): string
    {
        $n = mb_strtolower(trim(preg_replace('/\s+/', ' ', $name)));
        $map = ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ö'=>'o','ő'=>'o','ú'=>'u','ü'=>'u','ű'=>'u'];
        return strtr($n, $map);
    }
}
