<?php

namespace App\Observers;

use App\Enums\TimeEntryStatus;
use App\Enums\TimeEntryType;
use App\Models\TimeEntry;
use App\Services\Overtime\OvertimeBalanceService;
use Illuminate\Support\Facades\Auth;

class TimeEntryObserver
{
    public function __construct(private OvertimeBalanceService $service)
    {
    }

    /**
     * Egyetlen elszámolási pont: bármelyik úton (admin gomb, terminál webhook,
     * műszak-tábla, TimeEntryResource űrlap) záródik le egy jelenléti bejegyzés,
     * itt számoljuk ki az órákat és a túlóra-keret módosítását.
     *
     * needs_review=true esetén (pl. 12 órás auto-kiléptetés) a keretet nem
     * érintjük, amíg egy admin jóvá nem hagyja a bejegyzést.
     */
    public function saving(TimeEntry $entry): void
    {
        $this->trackManualCorrection($entry);

        if ($entry->type === TimeEntryType::Presence) {
            $this->settlePresence($entry);
            return;
        }

        // Napi import: túlóra-keret terhére elszámolt hiányzás (negatív órával jelölve).
        // Csak jóváhagyáskor terheli ténylegesen a keretet, ld. daily-import absence logika.
        if ($entry->type === TimeEntryType::Overtime && (float) ($entry->hours ?? 0) < 0) {
            $this->settleOvertimeConsumption($entry);
        }
    }

    /**
     * Utólagos, emberi javítás jelölése (jelenléti íven * jelöléshez): egy már korábban
     * rögzített idő/dátum/típus/óra érték módosítása, vagy egy felülvizsgálandó
     * (auto-kiléptetett/hiányos) bejegyzés jóváhagyása. Az első alkalommal kitöltött mező
     * (pl. rendes kiléptetés, vagy az import maga) NEM számít javításnak.
     */
    private function trackManualCorrection(TimeEntry $entry): void
    {
        if (! $entry->exists || ! Auth::check()) {
            return;
        }

        $correctedField = false;
        foreach (['start_date', 'start_time', 'raw_start_time', 'end_date', 'end_time', 'hours', 'type'] as $field) {
            if ($entry->isDirty($field) && $entry->getOriginal($field) !== null) {
                $correctedField = true;
                break;
            }
        }

        $reviewResolved = $entry->isDirty('needs_review')
            && $entry->getOriginal('needs_review')
            && ! $entry->needs_review;

        if ($correctedField || $reviewResolved) {
            $entry->is_modified = true;
            $entry->modified_by = Auth::id();
        }
    }

    private function settlePresence(TimeEntry $entry): void
    {
        if ($entry->status !== TimeEntryStatus::CheckedOut) {
            return;
        }
        if (!$entry->start_date || !$entry->start_time || !$entry->end_date || !$entry->end_time) {
            return;
        }

        $worked = $this->service->workedMinutes($entry);
        $entry->hours = round($worked / 60, 2);

        if ($entry->needs_review) {
            return;
        }

        $wasSettled = (bool) $entry->getOriginal('overtime_settled_at');
        $newDelta = $this->service->deltaMinutes($worked);

        if ($wasSettled) {
            $oldDelta = (int) $entry->getOriginal('overtime_delta_minutes');
            if ($newDelta === $oldDelta) {
                return;
            }
            // Korrekció: a régi hatást visszavonjuk, az újat alkalmazzuk.
            $this->service->applyDelta($entry->employee_id, $entry->company_id, $newDelta - $oldDelta);
        } else {
            $this->service->applyDelta($entry->employee_id, $entry->company_id, $newDelta);
        }

        $entry->overtime_delta_minutes = $newDelta;
        $entry->overtime_settled_at = now();
    }

    /**
     * Egy napi importból származó, túlóra-keret terhére elszámolt hiányzás
     * jóváhagyásakor vonja le a bankolt túlóra-percet az egyenlegből.
     * Amíg a bejegyzés 'pending', nem érinti a keretet.
     */
    private function settleOvertimeConsumption(TimeEntry $entry): void
    {
        if ($entry->status !== TimeEntryStatus::Approved) {
            return;
        }

        $minutes = (int) round(((float) $entry->hours) * 60); // negatív érték

        $wasSettled = (bool) $entry->getOriginal('overtime_settled_at');
        if ($wasSettled) {
            $oldMinutes = (int) round(((float) $entry->getOriginal('hours')) * 60);
            if ($minutes === $oldMinutes) {
                return;
            }
            $this->service->applyDelta($entry->employee_id, $entry->company_id, $minutes - $oldMinutes);
        } else {
            $this->service->applyDelta($entry->employee_id, $entry->company_id, $minutes);
        }

        $entry->overtime_settled_at = now();
    }
}
