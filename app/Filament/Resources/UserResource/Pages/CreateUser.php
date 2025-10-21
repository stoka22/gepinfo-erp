<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function afterCreate(): void
    {
        // A form teljes (raw) state-je – akkor is megkapjuk, ha a mező nem dehidratál
        $data     = $this->form->getRawState();
        $user     = $this->record; // létrehozott User
        $selected = $data['employee_link_id'] ?? null;

        DB::transaction(function () use ($user, $selected) {
            if (! $selected) {
                // nincs hozzárendelés kérve → semmit nem bontunk
                return;
            }

            // 1) ugyanahhoz a userhez kötött EGYÉB employee-k leválasztása
            Employee::where('account_user_id', $user->id)
                ->where('id', '!=', $selected)
                ->update(['account_user_id' => null]);

            // 2) kiválasztott employee ↔ user összekötés
            Employee::whereKey($selected)->update(['account_user_id' => $user->id]);
        });
    }
}
