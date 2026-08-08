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
