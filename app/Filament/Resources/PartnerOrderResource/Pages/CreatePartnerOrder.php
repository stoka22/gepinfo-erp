<?php

namespace App\Filament\Resources\PartnerOrderResource\Pages;

use App\Filament\Resources\PartnerOrderResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePartnerOrder extends CreateRecord
{
    protected static string $resource = PartnerOrderResource::class;

    /**
     * Mentés után frissítjük a fej-összesítőket.
     */
    protected function afterCreate(): void
    {
        /** @var \App\Models\PartnerOrder $order */
        $order = $this->record;
        // Ha később lesz ÁFA/árképzés logika, itt is meghívható
        $order->recalcTotals();
    }
}
