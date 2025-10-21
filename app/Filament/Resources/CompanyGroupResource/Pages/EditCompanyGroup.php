<?php
// app/Filament/Resources/CompanyGroupResource/Pages/EditCompanyGroup.php

namespace App\Filament\Resources\CompanyGroupResource\Pages;

use App\Filament\Resources\CompanyGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCompanyGroup extends EditRecord
{
    protected static string $resource = CompanyGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->label('Törlés'),
        ];
    }

    public function getTitle(): string
    {
        return 'Cégcsoport szerkesztése';
    }
}
