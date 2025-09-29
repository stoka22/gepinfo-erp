<?php

// app/Filament/Resources/PartnerResource/Pages/CreatePartner.php
namespace App\Filament\Resources\PartnerResource\Pages;

use App\Filament\Resources\PartnerResource;
use App\Models\Partner;
use Filament\Resources\Pages\CreateRecord;
use Filament\Facades\Filament; // <-- EZ KELL

class CreatePartner extends CreateRecord
{
    protected static string $resource = PartnerResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = Filament::auth()->user(); // <-- ITT
        if (!$user?->isAdmin() && $user?->company_id) {
            $data['owner_company_id'] = $user->company_id;
        }
        return $data;
    }

    protected function afterCreate(): void
    {
        $user = Filament::auth()->user(); // <-- ITT
        /** @var Partner $partner */
        $partner = $this->record;

        // létrehozáskor automatikusan rendeljük a saját céghez
        if ($user?->company) {
            $partner->companies()->syncWithoutDetaching([$user->company_id]);
        }
    }
}
