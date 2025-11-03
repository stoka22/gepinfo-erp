<?php

namespace App\Services;

use App\Models\CardImportRow;
use App\Models\Employee;
use App\Models\EmployeeCard;

class CardApplyService
{
    /**
     * Élesítés:
     * - Csak aktív (employees.is_disabled = 0) dolgozóra kötünk.
     * - Ha az UID aktív dolgozónál van: duplicate.
     * - Ha csak inaktívnál volt: automatikus újraosztás.
     * - Ugyanannál a dolgozónál már aktív ugyanaz az UID → already_linked.
     * - Kapcsolatmentes: JOIN-okat használunk.
     */
    public function apply(int $importId): array
    {
        $rows = CardImportRow::query()
            ->where('card_import_id', $importId)
            ->whereIn('status', ['auto', 'linked'])
            ->whereNotNull('matched_employee_id')
            ->get();

        $created = 0;
        $skipped = 0;
        $reassigned = 0;
        $alreadyLinked = 0;
        $duplicates = 0;
        $inactiveTarget = 0;

        foreach ($rows as $r) {
            $targetEmployeeId = (int) $r->matched_employee_id;

            $targetEmployee = Employee::find($targetEmployeeId);
            if (! $targetEmployee || $targetEmployee->is_disabled) {
                $r->update(['status' => 'inactive_employee']);
                $inactiveTarget++;
                $skipped++;
                continue;
            }

            $rawUid = trim((string) $r->raw_uid);
            if ($rawUid === '') {
                $r->update(['status' => 'invalid_uid']);
                $skipped++;
                continue;
            }

            $normUid = $this->normalizeUid($rawUid);

            // Már ennél a dolgozónál aktív ugyanez az UID?
            $sameActiveForTarget = EmployeeCard::query()
                ->where('employee_id', $targetEmployeeId)
                ->where('card_uid', $normUid)
                ->where('active', true)
                ->first();

            if ($sameActiveForTarget) {
                $r->update(['status' => 'linked']);
                $alreadyLinked++;
                continue;
            }

            // Aktív MÁS dolgozónál?
            $existsForActiveOther = EmployeeCard::query()
                ->where('employee_cards.card_uid', $normUid)
                ->where('employee_cards.active', true)
                ->where('employee_cards.employee_id', '!=', $targetEmployeeId)
                ->join('employees', 'employees.id', '=', 'employee_cards.employee_id')
                ->where('employees.is_disabled', 0)
                ->exists();

            if ($existsForActiveOther) {
                $r->update(['status' => 'duplicate']);
                $duplicates++;
                $skipped++;
                continue;
            }

            // Csak inaktívnál volt → újraosztás
            $inactiveHolderCard = EmployeeCard::query()
                ->where('employee_cards.card_uid', $normUid)
                ->where('employee_cards.active', false)
                ->join('employees', 'employees.id', '=', 'employee_cards.employee_id')
                ->where('employees.is_disabled', 1)
                ->select('employee_cards.*')
                ->orderByDesc('employee_cards.id')
                ->first();

            if ($inactiveHolderCard) {
                // biztos ami biztos
                $inactiveHolderCard->update(['active' => false]);

                EmployeeCard::create([
                    'employee_id' => $targetEmployeeId,
                    'card_uid'    => $normUid,
                    'label'       => 'Importált (újraosztva)',
                    'type'        => null,
                    'active'      => true,
                    'assigned_at' => now(),
                ]);

                $meta = (array) $r->meta;
                $meta['applied_prev_inactive_holder'] = ['employee_id' => $inactiveHolderCard->employee_id];
                $meta['raw_uid_normalized'] = $normUid;

                $r->update(['status' => 'linked', 'meta' => $meta]);
                $reassigned++;
                $created++;
                continue;
            }

            // Új kiadás
            EmployeeCard::create([
                'employee_id' => $targetEmployeeId,
                'card_uid'    => $normUid,
                'label'       => 'Importált',
                'type'        => null,
                'active'      => true,
                'assigned_at' => now(),
            ]);

            $meta = (array) $r->meta;
            $meta['raw_uid_normalized'] = $normUid;

            $r->update(['status' => 'linked', 'meta' => $meta]);
            $created++;
        }

        return [
            'created'         => $created,
            'reassigned'      => $reassigned,
            'already_linked'  => $alreadyLinked,
            'duplicates'      => $duplicates,
            'inactive_target' => $inactiveTarget,
            'skipped'         => $skipped,
        ];
    }

    protected function normalizeUid(string $uid): string
    {
        $u = strtoupper($uid);
        return str_replace([' ', '-', ':', '.'], '', $u);
    }
}
