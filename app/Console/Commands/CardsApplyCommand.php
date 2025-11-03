<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\EmployeeCard;

class CardsApplyCommand extends Command
{
    protected $signature = 'cards:apply {importId}';
    protected $description = 'A stagingben lévő sorok élesítése: EmployeeCard rekordok létrehozása';

    public function handle(): int
    {
        $importId = (int) $this->argument('importId');

        $rows = \DB::table('card_import_rows')
            ->where('card_import_id', $importId)
            ->whereIn('status', ['auto','linked']) // auto: automatikus; linked: kézzel beállított
            ->whereNotNull('matched_employee_id')
            ->get();

        $created = 0; $skipped = 0;

        foreach ($rows as $r) {
            // duplikált UID esetén hagyjuk ki
            if (EmployeeCard::where('card_uid', $r->raw_uid)->exists()) {
                \DB::table('card_import_rows')->where('id', $r->id)->update(['status' => 'duplicate']);
                $skipped++; continue;
            }

            EmployeeCard::create([
                'employee_id' => $r->matched_employee_id,
                'card_uid'    => $r->raw_uid,
                'label'       => 'Importált',
                'type'        => null,
                'active'      => true,
                'assigned_at' => now(),
            ]);

            \DB::table('card_import_rows')->where('id', $r->id)->update(['status' => 'linked']);
            $created++;
        }

        $this->table(['Created','Skipped'], [[ $created, $skipped ]]);

        return self::SUCCESS;
    }
}
