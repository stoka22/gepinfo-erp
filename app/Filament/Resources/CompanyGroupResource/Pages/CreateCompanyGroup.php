<?php
// app/Filament/Resources/CompanyGroupResource/Pages/CreateCompanyGroup.php

namespace App\Filament\Resources\CompanyGroupResource\Pages;

use App\Filament\Resources\CompanyGroupResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCompanyGroup extends CreateRecord
{
    protected static string $resource = CompanyGroupResource::class;

    public function getTitle(): string
    {
        return 'Cégcsoport létrehozása';
    }
}
