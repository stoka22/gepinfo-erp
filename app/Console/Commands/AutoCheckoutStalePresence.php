<?php

namespace App\Console\Commands;

use App\Enums\TimeEntryStatus;
use App\Enums\TimeEntryType;
use App\Models\TimeEntry;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AutoCheckoutStalePresence extends Command
{
    protected $signature = 'attendance:auto-checkout';
    protected $description = '12 óránál régebb óta nyitva álló jelenléti bejegyzések automatikus, felülvizsgálatra jelölt lezárása';

    private const MAX_OPEN_HOURS = 12;

    public function handle(): int
    {
        $open = TimeEntry::query()
            ->where('type', TimeEntryType::Presence->value)
            ->where('status', TimeEntryStatus::CheckedIn->value)
            ->whereNotNull('start_date')
            ->whereNotNull('start_time')
            ->whereNull('end_date')
            ->whereNull('end_time')
            // A munkanapló-importból (worklog-import) származó, kilépés nélküli sorok NEM
            // "valaki most van bent és elfelejtett kiléptetni" esetek — ezek eleve régi,
            // már lezajlott (hetekkel/hónapokkal korábbi) műszakok, ahol a forrásfájl
            // egyszerűen nem tartalmazott kilépést. Ha ez a parancs ezeket is kitalált
            // kezdés+12h időponttal lezárná, hamis "Vége" időpont kerülne a jelenléti ívre
            // — élesben azonosítva (Bolics Péter, 2026-08), miután a hiányzó schedule:run
            // cron pótlása után ez a job először tudott ténylegesen lefutni, és percek
            // alatt lezárt 539, korábban importált, importáláskor eleve `needs_review`-ra
            // jelölt nyitott sort. Csak a valós (terminálos/kioszkos) beléptetéseket zárja.
            ->where(fn ($q) => $q->whereNull('entry_method')->orWhere('entry_method', '!=', 'worklog-import'))
            ->get();

        $closed = 0;

        foreach ($open as $entry) {
            $checkedInAt = Carbon::parse(
                $entry->start_date->toDateString() . ' ' . $entry->start_time->format('H:i:s')
            );
            $forcedOutAt = $checkedInAt->copy()->addHours(self::MAX_OPEN_HOURS);

            if ($forcedOutAt->isFuture()) {
                continue;
            }

            $entry->end_date = $forcedOutAt->toDateString();
            $entry->end_time = $forcedOutAt->format('H:i:s');
            $entry->status = TimeEntryStatus::CheckedOut->value;
            $entry->needs_review = true;
            $entry->note = trim(($entry->note ? $entry->note . '; ' : '') . 'Automatikus kiléptetés 12 óra után – ellenőrzést igényel.');
            $entry->save();

            $closed++;
        }

        $this->info("Automatikusan lezárt, felülvizsgálatra jelölt bejegyzések: {$closed}");

        return self::SUCCESS;
    }
}
