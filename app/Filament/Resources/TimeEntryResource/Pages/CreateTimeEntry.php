<?php

namespace App\Filament\Resources\TimeEntryResource\Pages;

use App\Filament\Resources\TimeEntryResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;
use App\Enums\TimeEntryType;
use Illuminate\Validation\ValidationException;

class CreateTimeEntry extends CreateRecord
{
    protected static string $resource = TimeEntryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // employee alapján biztos company_id
        if (! empty($data['employee_id'])) {
            $employeeCompanyId = \App\Models\Employee::withoutGlobalScopes()
                ->whereKey($data['employee_id'])
                ->value('company_id');

            if ($employeeCompanyId) {
                $data['company_id'] = $employeeCompanyId;
            }
        }

        // státusz szinkron
        if (($data['type'] ?? null) === \App\Enums\TimeEntryType::Presence->value) {
            if (! in_array($data['status'] ?? null, [
                \App\Enums\TimeEntryStatus::CheckedIn->value,
                \App\Enums\TimeEntryStatus::CheckedOut->value,
            ], true)) {
                $data['status'] = \App\Enums\TimeEntryStatus::CheckedIn->value;
            }

            // Az "end_date" mező jelenlétnél rejtett az űrlapon (csak a többnapos
            // távollét-típusoknál látszik) — enélkül egy kilépési idővel rendelkező, de
            // korábban hiányos (pl. auto-kiléptetett) bejegyzésen sosem lehetne beállítani,
            // és a TimeEntryObserver::settlePresence() teljesség-ellenőrzése örökre
            // elbukna rajta. A jelenlét mindig egynapos, a kilépés dátuma = a belépésé.
            $data['end_date'] = filled($data['end_time'] ?? null) ? ($data['start_date'] ?? null) : null;
        } else {
            if (in_array($data['status'] ?? null, [
                \App\Enums\TimeEntryStatus::CheckedIn->value,
                \App\Enums\TimeEntryStatus::CheckedOut->value,
            ], true)) {
                $data['status'] = \App\Enums\TimeEntryStatus::Pending->value;
            }

            $data['start_time'] = null;
            $data['end_time'] = null;
        }

        if (($data['type'] ?? null) !== \App\Enums\TimeEntryType::Overtime->value) {
            $data['hours'] = null;
        }

        return $data;
    }

protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
{
    return \App\Models\TimeEntry::withoutGlobalScope('company')->create($data);
}

    protected function afterCreate(): void
    {
        Notification::make()->title('Bejegyzés létrehozva')->success()->send();
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
