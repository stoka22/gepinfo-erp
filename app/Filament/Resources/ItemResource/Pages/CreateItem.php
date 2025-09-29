<?php

namespace App\Filament\Resources\ItemResource\Pages;

use App\Filament\Resources\ItemResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateItem extends CreateRecord
{
    protected static string $resource = ItemResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $u = Filament::auth()->user();
        if (!$u?->isAdmin()) {
            $data['company_id'] = $u->company_id;
        } else {
            // admin is a saját cégére készít – vagy tegyél ide Selectet a formba, ha több cég van
            $data['company_id'] = $u?->company_id;
        }
        return $data;
    }
}
