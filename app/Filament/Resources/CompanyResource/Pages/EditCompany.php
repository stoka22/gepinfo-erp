<?php

namespace App\Filament\Resources\CompanyResource\Pages;

use App\Filament\Resources\CompanyResource;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;

class EditCompany extends EditRecord
{
    protected static string $resource = CompanyResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // előtöltjük a jelenlegi cég felhasználóit a többválasztóba
        $data['users_to_attach'] = $this->record->users()->pluck('id')->all();
        return $data;
    }

    protected function afterSave(): void
    {
        $company = $this->record;
        $ids = (array) data_get($this->data, 'users_to_attach', []);
        $ids = array_values(array_filter(array_map('intval', $ids)));

        // először leválasztjuk azokat, akik már nem szerepelnek
        User::where('company_id', $company->id)
            ->whereNotIn('id', $ids)
            ->update(['company_id' => null]);

        // majd hozzárendeljük az új listát
        if (! empty($ids)) {
            User::whereIn('id', $ids)->update(['company_id' => $company->id]);
        }
    }
}
