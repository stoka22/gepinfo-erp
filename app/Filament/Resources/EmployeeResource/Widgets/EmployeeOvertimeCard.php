<?php

namespace App\Filament\Resources\EmployeeResource\Widgets;

use App\Models\Employee;
use App\Models\OvertimeBalance;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EmployeeOvertimeCard extends StatsOverviewWidget
{
    // nem statikus!
    protected ?string $heading = 'Túlóra (óra)';

    public ?Employee $record = null;
    protected int|string|array $columnSpan = 1;

    protected static bool $isLazy = true;

    protected function getColumns(): int
    {
        // 2 doboz: éves + havi (mobilon 1, md-től 2 oszlop)
        return 2 ;
    }

    protected function getStats(): array
    {
        $eid = $this->record?->id ?? 0;

        $tightCentered = [
            // kisebb padding + egységes magasság + teljes középre igazítás
            'class' => 'p-2 h-24 flex flex-col items-center justify-center text-center gap-y-1
                        [&_.fi-stat-value]:leading-none [&_.fi-stat-value]:whitespace-nowrap',
        ];

        // A göngyölt túlóra-egyenleg nem évhez kötött (ld. OvertimeBalanceService::applyDelta
        // "Göngyölt egyenleg" doksorja) – a korábbi whereYear(now()->year) számítás ezért
        // szilveszterkor hamis, majdnem-0 értékre "ugrott vissza", miközben a valódi keret
        // (overtime_balances.balance_minutes) tovább görgetve, változatlanul élt. Itt a
        // forrás-igazságot (a ténylegesen vezetett egyenleget) mutatjuk, nem egy újraszámolt
        // közelítést.
        $balance = OvertimeBalance::where('employee_id', $eid)->first();
        $balanceHours = $balance ? $balance->effective_balance_minutes / 60 : 0.0;

        $monthly = 0.0;
        if (Schema::hasTable('time_entries')) {
            // A jelenlét (presence) bejegyzések automatikusan elszámolt, nettó
            // overtime_delta_minutes összege (ld. TimeEntryObserver) – a 8:30 alatti
            // (negatív) napok is beleszámítanak, nem csak a ténylegesen túlórázottak.
            $monthly = (float) DB::table('time_entries')
                ->where('employee_id', $eid)
                ->where('type', 'presence')
                ->whereBetween('start_date', [
                    now()->startOfMonth()->toDateString(),
                    now()->endOfMonth()->toDateString(),
                ])
                ->whereNotNull('overtime_delta_minutes')
                ->sum('overtime_delta_minutes') / 60;
        }

        return [
            Stat::make('Aktuális egyenleg', number_format($balanceHours, 1))->extraAttributes($tightCentered),
            Stat::make('Aktuális havi változás', number_format($monthly, 1))->extraAttributes($tightCentered),
        ];
    }
}
