<?php
// app/Services/CardService.php

namespace App\Services;

use App\Models\Card;
use App\Models\Employee;
use Illuminate\Validation\ValidationException;

class CardService
{
    public function assignByUid(int $employeeId, string $uid): Card
    {
        $uid = $this->normalizeUid($uid);

        $employee = Employee::findOrFail($employeeId);

        // A dolgozónak már van kártyája?
        if (Card::where('employee_id', $employee->id)->exists()) {
            throw ValidationException::withMessages([
                'employee_id' => 'A dolgozóhoz már tartozik kártya.',
            ]);
        }

        // Kártya létezzen és ne legyen másnál
        $card = Card::where('uid', $uid)->first();
        if (! $card) {
            throw ValidationException::withMessages([
                'uid' => 'A megadott kártya (UID) nem létezik a rendszerben.',
            ]);
        }
        if ($card->employee_id && $card->employee_id !== $employee->id) {
            throw ValidationException::withMessages([
                'uid' => 'A kártya már másik dolgozóhoz tartozik.',
            ]);
        }
        if ($card->status === 'blocked' || $card->status === 'lost') {
            throw ValidationException::withMessages([
                'uid' => 'A kártya blokkolt vagy elveszett státuszú.',
            ]);
        }

        $card->update([
            'employee_id' => $employee->id,
            'assigned_at' => now(),
            'status'      => 'assigned',
        ]);

        return $card->fresh();
    }

    public function unassign(int $cardId): Card
    {
        $card = Card::findOrFail($cardId);
        $card->update([
            'employee_id' => null,
            'assigned_at' => null,
            'status'      => 'available',
        ]);
        return $card->fresh();
    }

    public function markLost(int $cardId): Card
    {
        $card = Card::findOrFail($cardId);
        $card->update(['status' => 'lost']);
        return $card->fresh();
    }

    public function block(int $cardId): Card
    {
        $card = Card::findOrFail($cardId);
        $card->update(['status' => 'blocked']);
        return $card->fresh();
    }

    protected function normalizeUid(string $uid): string
    {
        $u = strtoupper($uid);
        return str_replace([' ', '-', ':', '.'], '', $u);
    }
}
