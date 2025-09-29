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
        $data['requested_by'] = $data['requested_by'] ?? Auth::id();

        // employee -> company
        $employeeCompanyId = null;
        if (!empty($data['employee_id'])) {
            if (Schema::hasColumn('employees', 'company_id')) {
                $employeeCompanyId = \App\Models\Employee::query()
                    ->whereKey($data['employee_id'])
                    ->value('company_id');
            } elseif (Schema::hasColumn('employees', 'user_id') && Schema::hasColumn('users', 'company_id')) {
                $employeeCompanyId = DB::table('employees')
                    ->join('users', 'users.id', '=', 'employees.user_id')
                    ->where('employees.id', $data['employee_id'])
                    ->value('users.company_id');
            }
        }

        $currentUserCompanyId = Auth::user()?->company_id;
        $data['company_id'] = $employeeCompanyId ?? $currentUserCompanyId;

        // Tenant-védelem: ha ismert az employee cég és eltér a user cégétől
        if ($employeeCompanyId && $currentUserCompanyId && (int)$employeeCompanyId !== (int)$currentUserCompanyId) {
            throw ValidationException::withMessages([
                'employee_id' => 'Más cég dolgozójához nem rögzíthető bejegyzés.',
            ]);
        }

        // Típusfüggő normalizálás
        if (($data['type'] ?? null) === TimeEntryType::Overtime->value) {
            $data['end_date'] = null;
            if (isset($data['hours'])) $data['hours'] = (float) $data['hours'];
        } else {
            $data['hours'] = null;
        }

        return $data;
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
