<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Item extends Model
{
    protected $fillable = [
        'company_id','sku','name','unit','kind','is_active'
    ];

    protected $casts = [
        'is_active' => 'bool',
    ];

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }

    public function machines(): BelongsToMany
    {
        return $this->belongsToMany(Machine::class, 'item_machine')->withTimestamps();
    }

    // BOM: ha ez késztermék, ezek a komponensei
    public function bomComponents()
    {
        return $this->hasMany(BomComponent::class, 'product_item_id');
    }

    // BOM: ha ez komponens, ezekben a késztermékekben szerepel
    public function usedInProducts()
    {
        return $this->hasMany(BomComponent::class, 'component_item_id');
    }

    public function workSteps()
    {
        return $this->hasMany(ItemWorkStep::class)->where('is_active', true)->orderBy('step_no');
    }

    /** Durva becslés: össz-idő mp-ben adott darabszámra (soros lépések). */
    public function estimatedDurationForQty(float $qty): int
    {
        $qty = max(0, $qty);
        $secs = 0.0;
        foreach ($this->workSteps as $s) {
            $secs += ($s->setup_time_sec ?? 0) + $qty * ($s->cycle_time_sec ?? 0);
        }
        return (int) round($secs);
    }

}
