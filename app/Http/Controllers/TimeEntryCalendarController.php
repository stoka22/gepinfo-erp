<?php

namespace App\Http\Controllers;

use App\Filament\Resources\TimeEntryResource;
use App\Models\TimeEntry;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TimeEntryCalendarController extends Controller
{
    public function __invoke(Request $request)
    {
        [$start, $end] = $this->parseRange($request);

        $accessibleCompanyIds = TimeEntryResource::accessibleCompanyIds();
        $selectedCompanyId = $request->query('company_id', 'all');

        $types = $this->normalizeTypesFromRequest($request);

        $q = TimeEntry::withoutGlobalScope('company')
            ->whereIn('company_id', $accessibleCompanyIds)
            ->when(
                filled($selectedCompanyId) && $selectedCompanyId !== 'all',
                function ($qq) use ($selectedCompanyId, $accessibleCompanyIds) {
                    $companyId = (int) $selectedCompanyId;

                    if (in_array($companyId, $accessibleCompanyIds, true)) {
                        $qq->where('company_id', $companyId);
                    }
                }
            )
            ->when(! empty($types), fn ($qq) => $qq->whereIn('type', $types))
            ->where(function ($qq) use ($start, $end) {
                $qq->whereBetween('start_date', [$start, $end])
                    ->orWhereBetween('end_date', [$start, $end])
                    ->orWhere(function ($q2) use ($start, $end) {
                        $q2->where('start_date', '<=', $start)
                            ->where('end_date', '>=', $end);
                    });
            })
            ->with([
                'employee:id,name',
                'company:id,name',
            ])
            ->orderBy('start_date');

       $rawDebugStats = DB::table('time_entries')
    ->selectRaw('company_id, type, COUNT(*) as db')
    ->whereIn('company_id', $accessibleCompanyIds)
    ->where(function ($qq) use ($start, $end) {
        $qq->whereBetween('start_date', [$start, $end])
            ->orWhereBetween('end_date', [$start, $end])
            ->orWhere(function ($q2) use ($start, $end) {
                $q2->where('start_date', '<=', $start)
                    ->where('end_date', '>=', $end);
            });
    })
    ->groupBy('company_id', 'type')
    ->orderBy('company_id')
    ->orderBy('type')
    ->get();

logger()->info('calendar RAW DB grouped stats', [
    'accessible_company_ids' => $accessibleCompanyIds,
    'start' => $start,
    'end' => $end,
    'rows' => $rawDebugStats,
]);

        $events = $q->get()->map(function (TimeEntry $e) {
            $type = $e->type instanceof \BackedEnum ? $e->type->value : $e->type;
            $status = $e->status instanceof \BackedEnum ? $e->status->value : $e->status;

            $startStr = $e->start_date instanceof Carbon
                ? $e->start_date->toDateString()
                : (string) $e->start_date;

            $endBase = $e->end_date ?: $e->start_date;
            $endStr = ($endBase instanceof Carbon ? $endBase : Carbon::parse($endBase))
                ->copy()
                ->addDay()
                ->toDateString();

            $titleHuman = match ($type) {
                'vacation'   => 'Szabadság',
                'sick_leave' => 'Táppénz',
                'overtime'   => 'Túlóra',
                'presence'   => 'Jelenlét',
                default      => ucfirst(str_replace('_', ' ', (string) $type)),
            };

            $companyName = $e->company?->name ?? '—';

            $bg = match ($type) {
                'vacation'   => '#F59E0B',
                'overtime'   => '#38BDF8',
                'sick_leave' => '#EF4444',
                'presence'   => '#10B981',
                default      => '#9CA3AF',
            };

            return [
                'id' => (string) $e->id,
                'title' => ($e->employee?->name ?? 'Ismeretlen') . ' — ' . $titleHuman . ' — ' . $companyName,
                'start' => $startStr,
                'end' => $endStr,
                'allDay' => true,
                'backgroundColor' => $bg,
                'borderColor' => $bg,
                'textColor' => '#111827',
                'className' => ['te-' . $type, 'st-' . $status],
                'extendedProps' => [
                    'status' => $status,
                    'type' => $type,
                    'hours' => $e->hours,
                    'note' => $e->note,
                    'company_id' => $e->company_id,
                    'company_name' => $companyName,
                ],
            ];
        });

        return response()->json($events->values(), 200, ['Cache-Control' => 'no-store']);
    }

    private function normalizeTypesFromRequest(Request $request): array
    {
        $types = $request->query('types', []);

        if (is_array($types) && ! empty($types)) {
            $allowed = ['vacation', 'sick_leave', 'overtime', 'presence'];

            return array_values(array_intersect($allowed, $types));
        }

        $map = [
            'vacation'   => $request->boolean('vacation'),
            'sick_leave' => $request->boolean('sick_leave'),
            'overtime'   => $request->boolean('overtime'),
            'presence'   => $request->boolean('presence'),
        ];

        $selected = [];

        foreach ($map as $type => $enabled) {
            if ($enabled) {
                $selected[] = $type;
            }
        }

        return $selected;
    }

    private function parseRange(Request $request): array
    {
        try {
            $start = Carbon::parse($request->query('start'));
        } catch (\Throwable) {
            $start = now()->startOfMonth();
        }

        try {
            $end = Carbon::parse($request->query('end'));
        } catch (\Throwable) {
            $end = now()->endOfMonth();
        }

        if ($end->lt($start)) {
            [$start, $end] = [$end, $start];
        }

        if ($start->diffInDays($end) > 370) {
            $end = $start->copy()->addYear();
        }

        return [$start->toDateString(), $end->toDateString()];
    }
}