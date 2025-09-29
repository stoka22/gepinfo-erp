<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Employee;
use App\Models\VacationBalance;
use App\Services\Vacation\HuVacationCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RebuildVacationBalances extends Command
{
    // Laravel 11-ben elég a signature az autó-regisztrációhoz
    protected $signature = 'vacation:rebuild {year?}';
    protected $description = 'Éves szabadságkeretek újraszámolása és mentése (alap + életkor Mt. szerint)';

    public function handle(HuVacationCalculator $calc): int
    {
        $year = (int)($this->argument('year') ?? now()->year);

        // ha az Employee-nek van owner() kapcsolata (User), jó, mert onnan is tudunk company_id-t szedni
        $employees = Employee::query()->with('owner')->get();

        DB::transaction(function () use ($employees, $year, $calc) {
            foreach ($employees as $e) {
                // ← Ez számolja ki:  base (20 nap arányosítva) + age_extra (életkor)
                $res = $calc->calculate($e, $year);

                // company_id eldöntése: employees.company_id, különben users.company_id (owner)
                $companyId = null;
                if (Schema::hasColumn('employees', 'company_id')) {
                    $companyId = $e->company_id;
                } else {
                    $companyId = optional($e->owner)->company_id;
                }

                // ← Itt MENTJÜK el: CSAK az alap + életkor mezőket frissítjük.
                VacationBalance::updateOrCreate(
                    ['employee_id' => $e->id, 'year' => $year],
                    [
                        'company_id'     => $companyId,
                        'base_days'      => $res['base'],      // 20 nap arányosítva
                        'age_extra_days' => $res['age_extra'], // Mt. életkor pótszabi arányosítva
                        // IMPORTANT: a további pótszabikat NEM itt mentjük, hanem külön, a VacationAllowance táblából jönnek összeadáskor
                        // carried_over_days / manual_adjustment_days mezőket sem írjuk felül itt
                    ]
                );
            }
        });

        $this->info("Vacation balances rebuilt for {$year}.");
        return self::SUCCESS;
    }
}
