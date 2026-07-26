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
