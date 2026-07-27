<?php

namespace App\Console\Commands;

use App\Models\TimeEntry;
use App\Support\TimeRounding;
use Illuminate\Console\Command;

class BackfillRoundedStartTimes extends Command
{
    protected $signature = 'attendance:backfill-rounded-start {--employee=}';

    protected $description = 'A kerekítés bevezetése előtt importált jelenléti sorokra pótlólag beállítja a nyers bejelentkezést (raw_start_time), '
        .'és a start_time-ot fél órára felfelé kerekíti, hogy a ledolgozott/túlóra számítás helyes legyen.';

    public function handle(): int
    {
        $query = TimeEntry::query()
            ->where('entry_method', 'daily-import')
            ->whereNull('raw_start_time')
            ->whereNotNull('start_time');

        if ($id = $this->option('employee')) {
            $query->where('employee_id', (int) $id);
        }

        $entries = $query->get();
        $fixed = 0;

        foreach ($entries as $entry) {
            $raw = $entry->start_time->format('H:i');
            $rounded = TimeRounding::roundStartUpToHalfHour($raw);

            $entry->raw_start_time = $raw.':00';
            $entry->start_time = $rounded.':00';
            $entry->save();

            $fixed++;
        }

        $this->info("Javítva: {$fixed} sor.");

        return self::SUCCESS;
    }
}
