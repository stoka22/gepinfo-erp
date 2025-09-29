<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoodsReceipt extends Model
{
    protected $fillable = [
        'company_id','warehouse_id','supplier_partner_id','receipt_no',
        'occurred_at','currency','note','posted_at','created_by',
    ];

    protected $casts = [
        'occurred_at' => 'date',
        'posted_at'   => 'datetime',
    ];

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }
    public function supplier(): BelongsTo { return $this->belongsTo(Partner::class, 'supplier_partner_id'); }
    public function lines(): HasMany { return $this->hasMany(GoodsReceiptLine::class); }
}
