<?php

namespace App\Filament\Resources\TimeEntryResource\Widgets;

use App\Models\Employee;
use App\Models\TimeEntry;
use Carbon\Carbon;
use Filament\Widgets\Widget;

class TimeEntriesCalendar extends Widget
{
    // FONTOS: csak egyetlen $view legyen!
    protected static string $view = 'filament.resources.time-entry.widgets.absence-matrix';

    protected int|string|array $columnSpan = 'full';

    // Állapot
    public string $month;                 // 'Y-m'
    public array  $days   = [];           // [ ['date' => 'YYYY-MM-DD','day'=>11], ... ]
    public array  $rows   = [];           // [ ['id'=>1,'name'=>'...','cells'=>[date=>info|null]], ... ]

    // (Opcionális) szűrők – most egyszerűen meghagyjuk, később Selectre cserélhető
    public ?int    $onlyEmployeeId = null;
    public ?string $status         = null;  // pending|approved|rejected|null

    // Modal
    public bool   $showModal      = false;
    public array  $cellItems      = [];
    public string $cellDateLabel  = '';
    public string $employeeName   = '';

    public function mount(): void
    {
        $this->month = now()->format('Y-m');
        $this->loadData();
    }

    public function previousMonth(): void
    {
        $this->month = Carbon::parse($this->month . '-01')->subMonth()->format('Y-m');
        $this->loadData();
    }

    public function nextMonth(): void
    {
        $this->month = Carbon::parse($this->month . '-01')->addMonth()->format('Y-m');
        $this->loadData();
    }

    public function updatedStatus(): void
    {
        $this->loadData();
    }

    public function updatedOnlyEmployeeId(): void
    {
        $this->loadData();
    }

    public function openCell(int $employeeId, string $date): void
    {
        $this->employeeName  = Employee::find($employeeId)?->name ?? '';
        $this->cellDateLabel = Carbon::parse($date)->locale(app()->getLocale())->translatedFormat('Y. MMM d., l');

        $entries = TimeEntry::with('employee')
            ->where('employee_id', $employeeId)
            ->whereDate('start_date', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('end_date')->orWhereDate('end_date', '>=', $date);
            })
            ->get();

        $this->cellItems = $entries->map(function (TimeEntry $e) {
            $type   = $e->type   instanceof \BackedEnum ? $e->type->value   : $e->type;
            $status = $e->status instanceof \BackedEnum ? $e->status->value : $e->status;

            return [
                'id'     => $e->id,
                'type'   => $type,
                'status' => $status,
                'hours'  => $e->hours,
                'start'  => (string) $e->start_date,
                'end'    => $e->end_date ? (string) $e->end_date : null,
                'note'   => $e->note,
            ];
        })->values()->all();

        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    protected function loadData(): void
    {
        $from = Carbon::parse($this->month . '-01')->startOfMonth();
        $to   = $from->copy()->endOfMonth();

        // Napok
        $this->days = [];
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $this->days[] = ['date' => $d->toDateString(), 'day' => $d->day];
        }

        // Dolgozók
        $employees = Employee::query()
            ->when($this->onlyEmployeeId, fn ($q) => $q->where('id', $this->onlyEmployeeId))
            ->orderBy('name')
            ->get(['id', 'name']);

        // Hónapot érintő távollétek
        $entries = TimeEntry::query()
            ->when($this->onlyEmployeeId, fn ($q) => $q->where('employee_id', $this->onlyEmployeeId))
            ->when($this->status,       fn ($q) => $q->where('status', $this->status))
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('start_date', [$from, $to])
                  ->orWhereBetween('end_date', [$from, $to])
                  ->orWhere(function ($qq) use ($from, $to) {
                      $qq->where('start_date', '<=', $from)->where('end_date', '>=', $to);
                  });
            })
            ->get();

        // dolgozó szerinti index
        $byEmployee = [];
        foreach ($entries as $e) {
            $byEmployee[$e->employee_id][] = $e;
        }

        // sorok
        $rows = [];
        foreach ($employees as $emp) {
            $cells = [];
            foreach ($this->days as $day) {
                $cells[$day['date']] = null;
            }

            foreach ($byEmployee[$emp->id] ?? [] as $e) {
                $start = Carbon::parse($e->start_date)->max($from);
                $end   = ($e->end_date ? Carbon::parse($e->end_date) : Carbon::parse($e->start_date))->min($to);

                $type   = $e->type   instanceof \BackedEnum ? $e->type->value   : $e->type;
                $status = $e->status instanceof \BackedEnum ? $e->status->value : $e->status;

                for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
                    $key = $d->toDateString();
                    $cells[$key] = [
                        'type'   => $type,
                        'status' => $status,
                        'id'     => $e->id,
                        'hours'  => $e->hours,
                    ];
                }
            }

            $rows[] = [
                'id'    => $emp->id,
                'name'  => $emp->name,
                'cells' => $cells,
            ];
        }

        $this->rows = $rows;
    }

    protected function getViewData(): array
    {
        $label = Carbon::parse($this->month . '-01')
            ->locale(app()->getLocale())
            ->translatedFormat('Y. MMMM');

        return ['monthLabel' => $label];
    }
}
