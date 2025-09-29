<?php

namespace App\Filament\Resources\PartnerOrderResource\Pages;

use App\Filament\Resources\PartnerOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPartnerOrder extends EditRecord
{
    protected static string $resource = PartnerOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->label('Törlés'),
        ];
    }

    /**
     * Mentés után újraszámoljuk az összesítőket (és opcionálisan státuszt).
     */
    protected function afterSave(): void
    {
        /** @var \App\Models\PartnerOrder $order */
        $order = $this->record;

        // Fej-összesítők
        $order->recalcTotals();

        // (Opcionális) státusz számolás a sorok alapján:
        // - ha minden sor fulfilled -> completed
        // - ha van legalább egy partial és nincs nyitott -> partial
        // - ha bármelyik open és nincs produced -> confirmed vagy in_production
        // Ezt csak akkor hagyd, ha nincs saját workflow-od:
        if ($order->items()->count() > 0) {
            $allFulfilled = $order->items()->where('status', '!=', 'fulfilled')->doesntExist();
            $anyPartial   = $order->items()->where('status', 'partial')->exists();
            $anyProduced  = $order->items()->where('qty_produced', '>', 0)->exists();

            if ($allFulfilled) {
                $order->status = 'completed';
            } elseif ($anyPartial) {
                $order->status = 'partial';
            } elseif ($anyProduced) {
                $order->status = 'in_production';
            }
            $order->save();
        }
    }
}
