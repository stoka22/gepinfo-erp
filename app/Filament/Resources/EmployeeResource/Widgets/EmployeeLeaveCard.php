<?php

namespace App\Filament\Resources\EmployeeResource\Widgets;

use App\Models\Employee;
use App\Models\VacationBalance;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EmployeeLeaveCard extends Widget
{
    protected static string $view = 'filament.employee.leave-card';
    public ?Employee $record = null;
    protected int|string|array $columnSpan = 1;

    // milyen típusokat tekintünk szabadságnak a time_entries.type alapján
    private const VACATION_TYPES = ['vacation','leave','szabadsag'];

    public function getViewData(): array
    {
        $year = now()->year;
        $eid  = $this->record?->id ?? 0;

        $entitled = 0.0;
        $usedDays = 0.0;

        // 1) jogosultság (balance + allowances)
        if (Schema::hasTable('vacation_balances')) {
            $vb = VacationBalance::query()
                ->where('employee_id', $eid)->where('year', $year)->first();

            if ($vb) {
                $entitled += (float) ($vb->base_days ?? 0);
                $entitled += (float) ($vb->age_extra_days ?? 0);
                $entitled += (float) ($vb->carried_over_days ?? 0);
                $entitled += (float) ($vb->manual_adjustment_days ?? 0);
            }
        }
        if (Schema::hasTable('vacation_allowances')) {
            $entitled += (float) DB::table('vacation_allowances')
                ->where('employee_id', $eid)->where('year', $year)->sum('days');
        }

        // 2) felhasználás — előnyben a vacation_usages; ha nincs, akkor time_entries.type IN VACATION_TYPES
        if (Schema::hasTable('vacation_usages')) {
            $usedDays = (float) DB::table('vacation_usages')
                ->where('employee_id', $eid)->whereYear('date', $year)->sum('days');
        } elseif (Schema::hasTable('time_entries')) {
            $hours = (float) DB::table('time_entries')
                ->where('employee_id', $eid)
                ->whereYear('start_date', $year)
                ->whereIn('type', self::VACATION_TYPES)
                ->sum('hours');                         // itt már van hours mező
            $usedDays = round($hours / $this->hoursPerDay(), 2);
        }

        $available = max(0, round($entitled - $usedDays, 2));

        return [
            'year'      => $year,
            'entitled'  => number_format($entitled, 1),
            'used'      => number_format($usedDays, 1),
            'available' => number_format($available, 1),
        ];
    }

    protected function hoursPerDay(): float
    {
        return 8.0; // ha később lesz cégbeállítás, onnan olvasd
    }
}
