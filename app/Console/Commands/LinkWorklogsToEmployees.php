<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\WorkLog;
use App\Models\Employee;

class LinkWorklogsToEmployees extends Command
{
    protected $signature = 'worklogs:link-employees';
    protected $description = 'Összekapcsolja a WorkLog rekordokat az Employees rekordokkal név alapján';

    public function handle()
    {
        $worklogs = WorkLog::whereNull('employee_id')->get();
        $linked = 0;

        foreach ($worklogs as $log) {
            $employee = Employee::where('name', $log->nev)->first();

            if ($employee) {
                $log->employee_id = $employee->id;
                $log->save();
                $linked++;
            } else {
                $this->warn("Nem találtam egyezést: {$log->nev}");
            }
        }

        $this->info("Összekapcsolt rekordok: {$linked}");
    }
}
