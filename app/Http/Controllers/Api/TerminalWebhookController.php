<?php

namespace App\Http\Controllers\Api;

use App\Enums\TimeEntryStatus;
use App\Enums\TimeEntryType;
use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class TerminalWebhookController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $token = $request->header('X-Auth-Token');
        if (!$token || !hash_equals((string) config('services.terminal.secret'), $token)) {
            return response()->json(['ok' => false, 'error' => 'unauthorized'], 401);
        }

        $data = $request->validate([
            'card_uid'   => ['required', 'string'],
            'direction'  => ['required', 'in:in,out'],
            'timestamp'  => ['required', 'date'],
            'event_id'   => ['nullable', 'string'], // opcionális, de erősen ajánlott dedup-hoz
            'location'   => ['nullable', 'string', 'max:255'], // bejelentkezési helyszín (több telephely esetén)
        ]);

        $card = Card::with('employee')
            ->where('uid', $data['card_uid'])
            ->whereNotNull('employee_id')
            ->first();

        if (!$card || !$card->employee) {
            Log::warning('Terminal webhook: unknown or unassigned card', ['card_uid' => $data['card_uid']]);
            return response()->json(['ok' => false, 'error' => 'unknown_card'], 404);
        }

        $employee = $card->employee;
        // Ha a szolgáltató UTC-t vagy más eltolást küld (pl. "...Z"), konvertáljuk az alkalmazás
        // időzónájára, hogy a start_time/end_time mindig helyi (magyar) időt tükrözzön.
        $ts = Carbon::parse($data['timestamp'])->setTimezone(config('app.timezone'));

        // Idempotencia: ha a szolgáltató küld event_id-t, és már feldolgoztuk, ne duplikáljunk.
        if (!empty($data['event_id'])) {
            $exists = TimeEntry::where('employee_id', $employee->id)
                ->where('note', 'like', '%terminal_event_id=' . $data['event_id'] . '%')
                ->exists();
            if ($exists) {
                return response()->json(['ok' => true, 'duplicate' => true]);
            }
        }

        $requestedBy = $employee->account_user_id
            ?? User::where('company_id', $employee->company_id)->where('role', 'admin')->value('id')
            ?? User::where('role', 'admin')->value('id');

        if (!$requestedBy) {
            Log::error('Terminal webhook: no fallback user found for requested_by', ['employee_id' => $employee->id]);
            return response()->json(['ok' => false, 'error' => 'no_system_user'], 500);
        }

        $note = !empty($data['event_id']) ? 'terminal_event_id=' . $data['event_id'] : null;

        if ($data['direction'] === 'in') {
            $open = TimeEntry::where('employee_id', $employee->id)
                ->where('type', TimeEntryType::Presence->value)
                ->whereNull('end_time')
                ->whereNull('end_date')
                ->orderByDesc('id')
                ->first();

            if ($open) {
                // már van nyitott jelenlét ehhez a dolgozóhoz -> ne nyissunk másikat
                return response()->json(['ok' => true, 'ignored' => 'already_checked_in']);
            }

            TimeEntry::create([
                'employee_id'  => $employee->id,
                'company_id'   => $employee->company_id,
                'type'         => TimeEntryType::Presence->value,
                'status'       => TimeEntryStatus::CheckedIn->value,
                'start_date'   => $ts->toDateString(),
                'start_time'   => $ts->format('H:i:s'),
                'end_date'     => null,
                'end_time'     => null,
                'hours'        => null,
                'note'         => $note,
                'location'     => $data['location'] ?? null,
                'requested_by' => $requestedBy,
                'approved_by'  => $requestedBy,
            ]);
        } else {
            $open = TimeEntry::where('employee_id', $employee->id)
                ->where('type', TimeEntryType::Presence->value)
                ->whereNull('end_time')
                ->whereNull('end_date')
                ->orderByDesc('id')
                ->first();

            if (!$open) {
                Log::warning('Terminal webhook: check-out with no open presence entry', ['employee_id' => $employee->id]);
                return response()->json(['ok' => false, 'error' => 'no_open_entry'], 409);
            }

            // Az órák és a túlóra-keret módosítása a TimeEntryObserver-ben történik.
            $open->update([
                'end_date' => $ts->toDateString(),
                'end_time' => $ts->format('H:i:s'),
                'status'   => TimeEntryStatus::CheckedOut->value,
                'note'     => $note ?? $open->note,
            ]);
        }

        return response()->json(['ok' => true]);
    }
}
