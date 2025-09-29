<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceiptLine extends Model
{
    protected $fillable = ['goods_receipt_id','item_id','qty','unit_cost','line_total','note'];
    protected $casts = ['qty'=>'decimal:3','unit_cost'=>'decimal:4','line_total'=>'decimal:2'];

    public function receipt(): BelongsTo { return $this->belongsTo(GoodsReceipt::class, 'goods_receipt_id'); }
    public function item(): BelongsTo { return $this->belongsTo(Item::class); }
}
