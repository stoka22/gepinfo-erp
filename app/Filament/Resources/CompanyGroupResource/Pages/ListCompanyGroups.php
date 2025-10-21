<?php
// app/Filament/Resources/CompanyGroupResource/Pages/ListCompanyGroups.php

namespace App\Filament\Resources\CompanyGroupResource\Pages;

use App\Filament\Resources\CompanyGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCompanyGroups extends ListRecords
{
    protected static string $resource = CompanyGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Új cégcsoport'),
        ];
    }

    public function getTitle(): string
    {
        return 'Cégcsoportok';
    }
}
