<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionTask extends Model
{
    protected $fillable = [
        'partner_id','partner_order_id','partner_order_item_id',
        'item_id','workflow_id','workflow_step_id','machine_id',
        'qty','setup_seconds','run_seconds','starts_at','ends_at','status','note',
    ];
    protected $casts = ['starts_at'=>'datetime','ends_at'=>'datetime'];

    public function partner(): BelongsTo { return $this->belongsTo(Partner::class); }
    public function order(): BelongsTo { return $this->belongsTo(PartnerOrder::class,'partner_order_id'); }
    public function orderItem(): BelongsTo { return $this->belongsTo(PartnerOrderItem::class,'partner_order_item_id'); }
    public function item(): BelongsTo { return $this->belongsTo(Item::class); }
    public function workflow(): BelongsTo { return $this->belongsTo(Workflow::class); }
    public function workflowStep(): BelongsTo { return $this->belongsTo(ItemWorkStep::class); }
    public function machine(): BelongsTo { return $this->belongsTo(Machine::class); }
    public function workStep():BelongsTo{return $this->belongsTo(ItemWorkStep::class, 'item_work_step_id');}
    
}
