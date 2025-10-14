<?php

namespace App\Http\Controllers;

use App\Models\TimeEntry;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TimeEntryCalendarController extends Controller
{
    public function __invoke(Request $request)
    {
        [$start, $end] = $this->parseRange($request);

        $allowed = ['vacation','sick_leave','overtime','presence'];
        $types   = array_values(array_intersect($allowed, (array) $request->query('types', [])));

        $cid = Auth::user()?->company_id;

        $q = TimeEntry::query()
            ->when($cid, fn ($qq) => $qq->where('company_id', $cid))
            ->when($types, fn ($qq) => $qq->whereIn('type', $types))
            ->where(function ($qq) use ($start, $end) {
                $qq->whereBetween('start_date', [$start, $end])
                ->orWhereBetween('end_date',   [$start, $end])
                ->orWhere(fn ($q2) => $q2->where('start_date', '<=', $start)->where('end_date', '>=', $end))
                ->orWhere(fn ($q3) => $q3->whereNull('end_date')->where('start_date', '<=', $end));
            })
            ->with('employee:id,name')
            ->orderBy('start_date');

        $events = $q->get()->map(function (TimeEntry $e) {
            $type   = $e->type   instanceof \BackedEnum ? $e->type->value   : $e->type;
            $status = $e->status instanceof \BackedEnum ? $e->status->value : $e->status;

            $startStr = $e->start_date instanceof \Carbon\Carbon ? $e->start_date->toDateString() : (string) $e->start_date;
            $endBase  = $e->end_date ?: $e->start_date;
            $endStr   = ($endBase instanceof \Carbon\Carbon ? $endBase : \Carbon\Carbon::parse($endBase))
                        ->copy()->addDay()->toDateString();

            $titleHuman = match ($type) {
                'vacation'   => 'Szabadság',
                'sick_leave' => 'Táppénz',
                'overtime'   => 'Túlóra',
                'presence'   => 'Jelenlét',
                default      => ucfirst(str_replace('_',' ', (string) $type)),
            };

            $bg = match ($type) {
                'vacation'   => '#F59E0B',
                'overtime'   => '#38BDF8',
                'sick_leave' => '#EF4444',
                'presence'   => '#10B981',
                default      => '#9CA3AF',
            };

            return [
                'id'              => (string) $e->id,
                'title'           => ($e->employee?->name ?? 'Ismeretlen') . ' — ' . $titleHuman,
                'start'           => $startStr,
                'end'             => $endStr,   // allDay exclusive
                'allDay'          => true,
                'backgroundColor' => $bg,
                'borderColor'     => $bg,
                'textColor'       => '#111827',
                'className'       => ['te-'.$type, 'st-'.$status],
                'extendedProps'   => [
                    'status' => $status,
                    'type'   => $type,
                    'hours'  => $e->hours,
                    'note'   => $e->note,
                ],
            ];
        });

        return response()->json($events->values(), 200, ['Cache-Control' => 'no-store']);
    }


    private function parseRange(Request $request): array
    {
        try { $start = Carbon::parse($request->query('start')); } catch (\Throwable) { $start = now()->startOfMonth(); }
        try { $end   = Carbon::parse($request->query('end'));   } catch (\Throwable) { $end   = now()->endOfMonth(); }

        if ($end->lt($start)) { [$start, $end] = [$end, $start]; }
        if ($start->diffInDays($end) > 370) { $end = $start->copy()->addYear(); }

        return [$start->toDateString(), $end->toDateString()];
    }
}
