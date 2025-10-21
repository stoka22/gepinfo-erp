<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $data     = $this->form->getRawState();
        $user     = $this->record;
        $selected = $data['employee_link_id'] ?? null;

        DB::transaction(function () use ($user, $selected) {
            if (! $selected) {
                // ha üresre állítod, és explicit leválasztást szeretnél,
                // itt oldd a kötést (opcionális):
                // Employee::where('account_user_id', $user->id)->update(['account_user_id' => null]);
                return;
            }

            Employee::where('account_user_id', $user->id)
                ->where('id', '!=', $selected)
                ->update(['account_user_id' => null]);

            Employee::whereKey($selected)->update(['account_user_id' => $user->id]);
        });
    }
}
