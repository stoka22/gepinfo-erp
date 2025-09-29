<?php

namespace App\Models;

use App\Models\PartnerOrderItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionSplit extends Model
{
    protected $fillable = ['partner_order_item_id','qty','produced_at','notes'];

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(PartnerOrderItem::class, 'partner_order_item_id');
    }

    protected static function booted(): void
    {
        static::saved(function (self $split) {
            $split->orderItem?->recalcFromSplits();
            $split->orderItem?->order?->recalcTotals();
        });
        static::deleted(function (self $split) {
            $split->orderItem?->recalcFromSplits();
            $split->orderItem?->order?->recalcTotals();
        });
    }
}

