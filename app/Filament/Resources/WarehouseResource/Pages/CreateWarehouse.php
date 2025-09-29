<?php

namespace App\Filament\Resources\WarehouseResource\Pages;

use App\Filament\Resources\WarehouseResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateWarehouse extends CreateRecord
{
    protected static string $resource = WarehouseResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $u = Filament::auth()->user() ;

        if (empty($data['company_id'])) {
            $data['company_id'] = $u?->company_id;
        }

        if (empty($data['company_id'])) {
            throw ValidationException::withMessages([
                'company_id' => 'Hiányzik a cég azonosítója (company_id).',
            ]);
        }
        return $data;
    }
}
