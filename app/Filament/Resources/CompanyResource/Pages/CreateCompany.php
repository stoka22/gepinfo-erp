<?php

namespace App\Filament\Resources\CompanyResource\Pages;

use App\Filament\Resources\CompanyResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;

class CreateCompany extends CreateRecord
{
    protected static string $resource = CompanyResource::class;

    protected function afterCreate(): void
    {
        $company = $this->record;

        // a form állapotából kérjük le a kiválasztott user ID-kat
        $ids = (array) data_get($this->data, 'users_to_attach', []);
        $ids = array_values(array_filter(array_map('intval', $ids)));

        if (! empty($ids)) {
            User::whereIn('id', $ids)->update(['company_id' => $company->id]);
        }
    }
}
