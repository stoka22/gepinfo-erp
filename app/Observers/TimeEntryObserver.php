<?php

namespace App\Observers;

use App\Enums\TimeEntryStatus;
use App\Enums\TimeEntryType;
use App\Models\TimeEntry;
use App\Services\Overtime\OvertimeBalanceService;

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
        if ($entry->type !== TimeEntryType::Presence) {
            return;
        }
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
}
