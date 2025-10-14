<?php

namespace App\Models;

use App\Models\PartnerOrderItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionSplit extends Model
{
    protected $table = 'production_splits';

    protected $fillable = [
        'machine_id',
        'partner_order_item_id',
        'title',
        'start',
        'end',
        'qty_total',
        'qty_from',
        'qty_to',
        'rate_pph',
        'batch_size',
        'is_committed',
    ];

    
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

   protected $casts = [
        'start'        => 'datetime',
        'end'          => 'datetime',
        'is_committed' => 'boolean',
        'rate_pph'     => 'float',
        'qty_total'    => 'integer',
        'qty_from'     => 'integer',
        'qty_to'       => 'integer',
        'batch_size'   => 'integer',
    ];


    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class,'machine_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(PartnerOrderItem::class, 'partner_order_item_id');
    }

}

