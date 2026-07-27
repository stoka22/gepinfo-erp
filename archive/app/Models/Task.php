<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    protected $fillable = [
        'name','machine_id','order_item_id','starts_at','ends_at','setup_minutes',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at'   => 'datetime',
    ];

    public function predecessors(): HasMany
    {
        // azok a dependency rekordok, ahol EZ a successor
        return $this->hasMany(TaskDependency::class, 'successor_id');
    }

    public function successors(): HasMany
    {
        // ahol EZ a predecessor
        return $this->hasMany(TaskDependency::class, 'predecessor_id');
    }

    public function orderItem() { return $this->belongsTo(\App\Models\PartnerOrderItem::class, 'order_item_id'); }
}
