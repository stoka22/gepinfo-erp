<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BomComponent extends Model
{
    protected $fillable = ['product_item_id','component_item_id','qty_per_unit','note'];
    protected $casts = ['qty_per_unit'=>'decimal:3'];

    public function product(): BelongsTo { return $this->belongsTo(Item::class, 'product_item_id'); }
    public function component(): BelongsTo { return $this->belongsTo(Item::class, 'component_item_id'); }
}
