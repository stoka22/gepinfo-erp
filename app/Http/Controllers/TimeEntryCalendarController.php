<?php

namespace App\Http\Controllers;

use App\Models\TimeEntry;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TimeEntryCalendarController extends Controller
{
    public function __invoke(Request $request)
    {
        $start = Carbon::parse($request->query('start', now()->startOfMonth()));
        $end   = Carbon::parse($request->query('end',   now()->endOfMonth()));

        $entries = TimeEntry::query()
            ->whereDate('start_date', '<=', $end)
            ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $start))
            ->with('employee')
            ->get();

        $events = [];
        foreach ($entries as $e) {
            $type   = $e->type   instanceof \BackedEnum ? $e->type->value   : $e->type;
            $status = $e->status instanceof \BackedEnum ? $e->status->value : $e->status;

            $startStr = \Carbon\Carbon::parse($e->start_date)->toDateString();
            $endStr   = (\Carbon\Carbon::parse($e->end_date ?? $e->start_date))->addDay()->toDateString();

            $title = ($e->employee?->name ?? 'Ismeretlen') . ' — ' . match ($type) {
                'vacation'   => 'Szabadság',
                'sick_leave' => 'Táppénz',
                'overtime'   => 'Túlóra',
                default      => ucfirst(str_replace('_',' ', (string) $type)),
            };

            $color = match ($type) {
                'vacation'   => '#F59E0B',
                'sick_leave' => '#F43F5E',
                'overtime'   => '#3B82F6',
                default      => '#9CA3AF',
            };

            $events[] = [
                'title' => $title,
                'start' => $startStr,
                'end'   => $endStr,   // allDay → exkluzív
                'allDay'=> true,
                'color' => $color,
                'className' => ['te-'.$type, 'st-'.$status],
                'extendedProps' => [
                    'status' => $status,
                    'type'   => $type,
                    'hours'  => $e->hours,
                    'note'   => $e->note,
                ],
            ];
        }

        return response()->json($events);
    }
}
