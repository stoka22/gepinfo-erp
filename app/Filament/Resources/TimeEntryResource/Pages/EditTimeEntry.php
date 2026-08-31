<?php

namespace App\Filament\Resources\TimeEntryResource\Pages;

use App\Enums\TimeEntryStatus;
use App\Enums\TimeEntryType;
use App\Filament\Resources\TimeEntryResource;
use App\Models\Employee;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTimeEntry extends EditRecord
{
    protected static string $resource = TimeEntryResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // employee alapján biztos company_id (ld. CreateTimeEntry ugyanezen logikája).
        if (! empty($data['employee_id'])) {
            $employeeCompanyId = Employee::withoutGlobalScopes()
                ->whereKey($data['employee_id'])
                ->value('company_id');

            if ($employeeCompanyId) {
                $data['company_id'] = $employeeCompanyId;
            }
        }

        if (($data['type'] ?? null) === TimeEntryType::Presence->value) {
            // A jelenlét-bejegyzés státusza MINDIG a be-/kilépés adataiból vezetődik le, nem
            // hagyatkozunk arra, hogy a mentett érték (esetleg egy örökölt, hibás "pending")
            // helyes — enélkül egy hibás státuszú bejegyzésen a be-/kilépés javítása sosem
            // futtatná le a munkaidő/túlóra újraszámolását (ld. TimeEntryObserver::settlePresence,
            // ami KIZÁRÓLAG checked_out státuszon fut).
            $data['status'] = filled($data['end_time'] ?? null)
                ? TimeEntryStatus::CheckedOut->value
                : TimeEntryStatus::CheckedIn->value;

            // Az "end_date" mező jelenlétnél rejtett az űrlapon (csak a többnapos
            // távollét-típusoknál látszik), ezért sosem érkezik be a mentett adatok közt —
            // enélkül egy kilépési idővel most kiegészített, de korábban hiányos (pl.
            // auto-kiléptetett) bejegyzésen sosem lehetne beállítani, és a
            // TimeEntryObserver::settlePresence() teljesség-ellenőrzése örökre elbukna
            // rajta. A jelenlét mindig egynapos, a kilépés dátuma = a belépésé.
            $data['end_date'] = filled($data['end_time'] ?? null) ? ($data['start_date'] ?? null) : null;

            // Ha az admin ténylegesen megváltoztatta a belépés idejét, a raw_start_time (az
            // importból örökölt, nyers idő) elavulttá válik — a lista "Kezdet" oszlopa, a
            // jelenléti ív és a túlóra-számítás is MINDIG a raw_start_time-ot részesíti
            // előnyben a start_time-mal szemben (ld. TimeEntryTable, AttendanceSheetService,
            // OvertimeBalanceService — mind `raw_start_time ?? start_time` mintát használnak),
            // enélkül egy admin által javított start_time sosem látszana/számítana be sehol —
            // a mentett javítás láthatatlan maradna, végtelen "újbóli javítás" érzetét keltve.
            $newStart = filled($data['start_time'] ?? null) ? substr($data['start_time'], 0, 5) : null;
            $oldStart = $this->record->start_time?->format('H:i');
            if ($newStart !== $oldStart) {
                $data['raw_start_time'] = null;
            }
        } else {
            if (in_array($data['status'] ?? null, [
                TimeEntryStatus::CheckedIn->value,
                TimeEntryStatus::CheckedOut->value,
            ], true)) {
                $data['status'] = TimeEntryStatus::Pending->value;
            }

            $data['start_time'] = null;
            $data['end_time'] = null;

            if (($data['type'] ?? null) !== TimeEntryType::Overtime->value) {
                $data['hours'] = null;
            }
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return TimeEntryResource::getUrl('index');
    }
}
