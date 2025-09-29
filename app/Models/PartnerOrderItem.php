<?php

namespace App\Models;

use App\Models\Item;
use App\Models\PartnerOrder;
use App\Models\ProductionSplit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerOrderItem extends Model
{
    protected $fillable = [
        'partner_order_id','item_id','item_name_cache','unit',
        'qty_ordered','qty_reserved','qty_produced','qty_shipped',
        'unit_price','line_total','due_date','status',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $row) {
            if (empty($row->item_name_cache) && $row->item_id) {
                $item = \App\Models\Item::select('name','unit')->find($row->item_id);
                $row->item_name_cache = $item?->name ?? '';
                $row->unit = $row->unit ?: ($item?->unit ?? 'db');
            }
            $row->line_total = (float)($row->unit_price ?? 0) * (float)($row->qty_ordered ?? 0);
        });

        static::updating(function (self $row) {
            if ((empty($row->item_name_cache) || $row->isDirty('item_id')) && $row->item_id) {
                $item = \App\Models\Item::select('name','unit')->find($row->item_id);
                $row->item_name_cache = $item?->name ?? '';
                $row->unit = $row->unit ?: ($item?->unit ?? 'db');
            }
            $row->line_total = (float)($row->unit_price ?? 0) * (float)($row->qty_ordered ?? 0);
        });

        static::saving(function (self $row) {
            // line_total biztosítása (ha nincs még)
            $row->line_total = (float)($row->unit_price ?? 0) * (float)($row->qty_ordered ?? 0);

            // ETA frissítés
            if ($row->item_id && $row->qty_ordered) {
                // ha nincs előtöltve az item, töltsük (a workSteps-szel)
                $row->loadMissing(['item.workSteps']);
                $seconds = $row->item->estimatedDurationForQty((float) $row->qty_ordered);
                $row->est_duration_sec = $seconds;
                $row->est_finish_at = now()->addSeconds($seconds);
            }
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PartnerOrder::class, 'partner_order_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function splits(): HasMany
    {
        return $this->hasMany(ProductionSplit::class, 'partner_order_item_id');
    }

    public function recalcFromSplits(): void
    {
        $this->qty_produced = (float)$this->splits()->sum('qty');
        $this->status = $this->qty_produced <= 0 ? 'open'
            : ($this->qty_produced < $this->qty_ordered ? 'partial' : 'fulfilled');
        $this->
        
        save();
    }

    public function recalcEta(): void
    {
        if (!$this->item) return;
        $seconds = $this->item->estimatedDurationForQty((float) $this->qty_ordered);
        $this->est_duration_sec = $seconds;
        $this->est_finish_at = now()->addSeconds($seconds); // egyszerű „most + idő” becslés
        $this->save();
    }

   

}

