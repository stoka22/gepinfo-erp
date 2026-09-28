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

        // Egy felülvizsgálatra váró jelenlét-bejegyzés automatikusan jóváhagyottá válik,
        // amint egy admin ténylegesen javítja/kiegészíti a be-/kilépés valamelyik adatát,
        // és a bejegyzés emiatt most már teljes (van be- és kilépési dátum/idő is) — maga
        // a javítás számít jóváhagyásnak, nem kell hozzá külön "jóváhagyás" gomb. Szándékosan
        // NEM a lenti $correctedField-et használjuk (az csak egy korábban KITÖLTÖTT érték
        // módosítását számolja javításnak) — itt pont az a tipikus eset, hogy egy korábban
        // ÜRES (pl. auto-kiléptetésnél hiányzó) mező kerül most először kitöltésre.
        $timingFieldsTouched = collect(['start_date', 'start_time', 'end_date', 'end_time', 'status'])
            ->contains(fn (string $field) => $entry->isDirty($field));

        if ($timingFieldsTouched
            && $entry->needs_review
            && $entry->type === TimeEntryType::Presence
            && $entry->start_date && $entry->start_time && $entry->end_date && $entry->end_time
        ) {
            $entry->needs_review = false;
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

    /**
     * A jelenlét lezárásakor számoljuk el az órákat és a túlóra-keret módosítását.
     *
     * FONTOS: napi több be-/kilépés (pl. ebédszünet) esetén a 8:30-as szabályt a NAP ÖSSZES
     * lezárt szakaszának együttes ledolgozott idejére kell alkalmazni, nem erre a szakaszra
     * önmagában – különben minden szakasz külön "hiányos munkanapnak" tűnne, és a rendszer
     * többszörösen terhelné a keretet. Ezért a napi delta a nap összes szakaszára egyszer
     * kerül kiszámításra, és a különbséget mindig arra a szakaszra könyveljük, amelyik
     * éppen mentésre kerül – így a nap szakaszainak overtime_delta_minutes összege mindig
     * pontosan a helyes napi eltérést adja, függetlenül attól, hány szakasz van aznap.
     */
    private function settlePresence(TimeEntry $entry): void
    {
        if ($entry->status !== TimeEntryStatus::CheckedOut) {
            return;
        }
        if (!$entry->start_date || !$entry->start_time || !$entry->end_date || !$entry->end_time) {
            return;
        }

        $siblings = TimeEntry::where('employee_id', $entry->employee_id)
            ->where('type', TimeEntryType::Presence->value)
            ->whereDate('start_date', $entry->start_date->toDateString())
            ->when($entry->exists, fn ($q) => $q->where('id', '!=', $entry->id))
            ->get();

        // A nap ÖSSZES szakaszát (a mentés alatt álló bejegyzéssel együtt) egyszerre kell
        // időrendbe rakni, hogy a "nap első szakasza" (egész órás kerekítés) helyesen
        // dőljön el – ha csak a testvéreket néznénk külön, egy korábbi testvér tévesen
        // "másodiknak" tűnhetne, ha éppen ez a mentés alatt álló szakasz a nap legkorábbija.
        // FONTOS: concat() (nem push()!) – a push() a HELYSZÍNEN módosítaná a $siblings
        // kollekciót is, ami miatt az később tévesen saját magát (az entry-t) is
        // tartalmazná a lentebbi needs_review-ellenőrzésnél és a siblingsAppliedDelta
        // összegzésénél, duplán levonva/hozzáadva a saját korábbi deltáját.
        $allForDay = $siblings->concat([$entry]);
        $segmentMinutes = $this->service->segmentMinutesForDay($allForDay);
        $worked = $segmentMinutes[spl_object_id($entry)] ?? 0;
        $entry->hours = round($worked / 60, 2);

        if ($entry->needs_review) {
            return;
        }

        // Amíg a nap bármelyik szakasza felülvizsgálatra vár, a teljes napi ledolgozott idő
        // bizonytalan – nem számolunk el, amíg mindegyik szakasz rendezve nincs.
        if ($siblings->contains(fn (TimeEntry $s) => $s->needs_review)) {
            return;
        }

        // NEM array_sum($segmentMinutes) -- az egymásba ágyazott/átfedő aznapi szakaszoknál
        // (ld. totalWorkedMinutesForDay() dokblokkja) ez többszörösen számolná ugyanazt az időt.
        $totalWorked = $this->service->totalWorkedMinutesForDay($allForDay);
        $standardMinutes = $this->service->standardMinutesFor($entry->employee);
        $newDayDelta = $this->service->deltaMinutes($totalWorked, $standardMinutes);

        $siblingsAppliedDelta = (int) $siblings->sum(fn (TimeEntry $s) => (int) ($s->overtime_delta_minutes ?? 0));
        $newEntryDelta = $newDayDelta - $siblingsAppliedDelta;

        // A tényleges OvertimeBalance-frissítés a saved()-ben történik, MIUTÁN ez a
        // bejegyzés a végleges overtime_delta_minutes-szel ténylegesen lemezre került --
        // ld. saved() doc-kommentjét, miért NEM itt (relatív increment/applyDelta()) megy
        // a keret-módosítás.
        $entry->overtime_delta_minutes = $newEntryDelta;
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
        }

        // A tényleges OvertimeBalance-frissítés a saved()-ben történik -- ld. ott.
        $entry->overtime_settled_at = now();
    }

    /**
     * Az OvertimeBalance TÉNYLEGES újraszámolása -- SZÁNDÉKOSAN a saved() eseményben,
     * NEM a saving()-ban (ahol korábban egy relatív applyDelta()-hívás történt): a
     * saving() idején ez a bejegyzés MÉG NINCS lemezre írva a végleges
     * overtime_delta_minutes/hours értékével, ezért egy itt futtatott újraszámolás a
     * SAJÁT, még régi adatával számolna. A saved()-ben a bejegyzés már véglegesen
     * elmentve -- a recomputeBalance() ekkor a TELJES, aktuális adatbázis-állapotból
     * (nem egy korábbi, esetleg elcsúszott értékhez képesti increment-ből) számolja az
     * egyenleget, ezért kötegelt/ismételt mentés esetén is mindig helyes végeredményhez
     * konvergál -- ld. OvertimeBalanceService::recomputeBalance() doc-kommentjét.
     */
    public function saved(TimeEntry $entry): void
    {
        if ($entry->type === TimeEntryType::Presence && $entry->wasChanged('overtime_delta_minutes')) {
            $this->service->recomputeBalance($entry->employee_id, $entry->company_id);
            return;
        }

        // FONTOS: a trigger NEM az overtime_settled_at változására épül -- az egy
        // időbélyeg, és ha két elszámolás ugyanabban a másodpercben történik (pl. gyors
        // egymás utáni admin-korrekció, vagy teszt), a DB-oszlop pontossága miatt a
        // "régi" és "új" érték AZONOS lehet, ekkor wasChanged() hamisan false-t adna,
        // és egy valódi órakorrekció csendben elveszne az egyenlegből. A "hours"/
        // "status" mezők tényleges ÉRTÉKváltozása megbízható jelző.
        if ($entry->type === TimeEntryType::Overtime
            && (float) ($entry->hours ?? 0) < 0
            && $entry->status === TimeEntryStatus::Approved
            && ($entry->wasChanged('hours') || $entry->wasChanged('status'))
        ) {
            $this->service->recomputeBalance($entry->employee_id, $entry->company_id);
        }
    }
}
