<?php

namespace App\Filament\Resources\GoodsReceiptResource\Pages;

use App\Filament\Resources\GoodsReceiptResource;
use App\Models\GoodsReceiptLine;
use App\Models\StockLevel;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;

class CreateGoodsReceipt extends CreateRecord
{
    protected static string $resource = GoodsReceiptResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $u = Filament::auth()->user();
        $data['company_id'] = $u?->company_id;
        $data['created_by'] = $u?->id;
        return $data;
    }

    protected function afterCreate(): void
    {
        $receipt = $this->record;

        DB::transaction(function () use ($receipt) {
            foreach ($receipt->lines as $line) {
                // készletszint rekesz előkészítése
                $level = StockLevel::firstOrCreate([
                    'company_id'  => $receipt->company_id,
                    'warehouse_id'=> $receipt->warehouse_id,
                    'item_id'     => $line->item_id,
                ]);

                $oldQty   = (float)$level->qty;
                $oldAvg   = (float)$level->avg_cost;
                $rcvQty   = (float)$line->qty;
                $unitCost = (float)$line->unit_cost;

                $newQty = $oldQty + $rcvQty;
                $newAvg = $newQty > 0
                    ? (($oldQty * $oldAvg) + ($rcvQty * $unitCost)) / $newQty
                    : $unitCost; // ha eddig nulla volt

                $level->qty = $newQty;
                $level->avg_cost = round($newAvg, 4);
                $level->save();

                // sor total biztosan stimmeljen
                if ($line->line_total != $rcvQty * $unitCost) {
                    $line->line_total = $rcvQty * $unitCost;
                    $line->save();
                }
            }

            $receipt->posted_at = now();
            $receipt->save();
        });

        Notification::make()->title('Bevételezés könyvelve')->success()->send();
    }
}
