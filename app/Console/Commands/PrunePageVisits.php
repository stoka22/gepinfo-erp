<?php

namespace App\Console\Commands;

use App\Models\PageVisit;
use Illuminate\Console\Command;

class PrunePageVisits extends Command
{
    protected $signature = 'page-visits:prune';
    protected $description = 'A 12 hónapnál régebbi honlap-látogatási rekordok törlése (adatminimalizálás, lásd Süti- és adatkezelési tájékoztató)';

    private const RETENTION_MONTHS = 12;

    public function handle(): int
    {
        $deleted = PageVisit::where('created_at', '<', now()->subMonths(self::RETENTION_MONTHS))->delete();

        $this->info("Törölve: {$deleted} db, " . self::RETENTION_MONTHS . " hónapnál régebbi látogatási rekord.");

        return self::SUCCESS;
    }
}
