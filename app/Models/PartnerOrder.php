<?php

namespace App\Models;

use App\Models\Partner;
use App\Models\PartnerOrderItem;
use Filament\Forms\Components\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerOrder extends Model
{
    protected $fillable = [
        'partner_id','order_no','order_date','due_date','status',
        'currency','total_net','total_gross','notes',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PartnerOrderItem::class, 'partner_order_id');
    }

    public function scopeStatus(Builder $q, string $status): Builder
    {
        return $q->where('status', $status);
    }

    public function recalcTotals(): void
    {
        $sum = $this->items()->sum('line_total');
        $this->total_net = $sum;
        $this->total_gross = $sum; // ha lesz ÁFA, itt számold
        $this->save();
    }
}

