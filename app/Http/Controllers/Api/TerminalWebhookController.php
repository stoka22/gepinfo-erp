<?php

namespace App\Http\Controllers\Api;

use App\Enums\TimeEntryStatus;
use App\Enums\TimeEntryType;
use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\TerminalWebhookFailure;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class TerminalWebhookController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $token = $request->header('X-Auth-Token');
        if (!$token || !hash_equals((string) config('services.terminal.secret'), $token)) {
            $this->logFailure($request, 'unauthorized', 401, 'Hiányzó vagy hibás X-Auth-Token.');
            return response()->json(['ok' => false, 'error' => 'unauthorized'], 401);
        }

        try {
            $data = $request->validate([
                'card_uid'   => ['required', 'string'],
                'direction'  => ['required', 'in:in,out'],
                'timestamp'  => ['required', 'date'],
                'event_id'   => ['nullable', 'string'], // opcionális, de erősen ajánlott dedup-hoz
                'location'   => ['nullable', 'string', 'max:255'], // bejelentkezési helyszín (több telephely esetén)
            ]);
        } catch (ValidationException $e) {
            $this->logFailure($request, 'validation', 422, json_encode($e->errors(), JSON_UNESCAPED_UNICODE));
            throw $e;
        }

        $card = Card::with('employee')
            ->where('uid', $data['card_uid'])
            ->whereNotNull('employee_id')
            ->first();

        if (!$card || !$card->employee) {
            Log::warning('Terminal webhook: unknown or unassigned card', ['card_uid' => $data['card_uid']]);
            $this->logFailure($request, 'unknown_card', 404, 'A kártya nem ismert, vagy nincs dolgozóhoz rendelve.');
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
            $this->logFailure($request, 'no_system_user', 500, 'Nincs elérhető rendszerfelhasználó (requested_by) ehhez a dolgozóhoz.');
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
                $this->logFailure($request, 'no_open_entry', 409, 'Kilépés érkezett, de nincs nyitott belépés ehhez a dolgozóhoz.');
                return response()->json(['ok' => false, 'error' => 'no_open_entry'], 409);
            }

            // Az órák és a túlóra-keret módosítása a TimeEntryObserver-ben történik.
            // A note-hoz HOZZÁFŰZZÜK (nem felülírjuk!) a kilépés event_id-ját, hogy a
            // belépés eredeti terminal_event_id-ja megmaradjon a dedup-hoz — ellenkező
            // esetben egy utólag megismételt belépés-esemény már nem lenne felismerhető
            // duplikátumként, mert a note időközben a kilépés event_id-jára cserélődött volna.
            $combinedNote = $note ? trim(($open->note ? $open->note . ';' : '') . $note) : $open->note;

            $open->update([
                'end_date' => $ts->toDateString(),
                'end_time' => $ts->format('H:i:s'),
                'status'   => TimeEntryStatus::CheckedOut->value,
                'note'     => $combinedNote,
            ]);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * A sikertelen/nem tárolt beérkező kéréseket rögzíti diagnosztikai célból (pl. rosszul
     * konfigurált terminál esetén itt látszik, mit küldött valójában és miért utasítottuk el) —
     * a duplikátum/már-bejelentkezve válaszok NEM hibák, azokat itt szándékosan nem naplózzuk.
     */
    protected function logFailure(Request $request, string $errorCode, int $httpStatus, ?string $message = null): void
    {
        try {
            $payload = $request->all();

            TerminalWebhookFailure::create([
                'error_code'  => $errorCode,
                'http_status' => $httpStatus,
                'card_uid'    => $payload['card_uid'] ?? null,
                'direction'   => $payload['direction'] ?? null,
                'message'     => $message,
                'payload'     => $payload,
                'ip_address'  => $request->ip(),
                'created_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            // A diagnosztikai naplózás sosem akaszthatja meg a tényleges válasz visszaküldését.
            Log::error('Terminal webhook: failed to record failure log entry', ['exception' => $e->getMessage()]);
        }
    }
}
