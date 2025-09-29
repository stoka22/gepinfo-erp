<?php

namespace App\Filament\Resources\PartnerOrderResource\Pages;

use App\Filament\Resources\PartnerOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPartnerOrders extends ListRecords
{
    protected static string $resource = PartnerOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Új megrendelés'),
        ];
    }
}
