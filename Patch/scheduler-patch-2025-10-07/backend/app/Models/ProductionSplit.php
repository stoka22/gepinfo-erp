<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionSplit extends Model
{
    protected $table = 'production_splits';

    protected $fillable = [
        'production_task_id',    // optional if split created directly
        'machine_id',
        'resource_id',           // alias to machine_id for client compatibility
        'title',
        'start',
        'end',
        'qty_total',
        'qty_from',
        'qty_to',
        'rate_pph',
        'batch_size',
        'partner_name',
        'order_code',
        'product_sku',
        'operation_name',
        'is_committed',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start' => 'datetime',
        'end'   => 'datetime',
        'is_committed' => 'boolean',
    ];

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }
}
