<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItemWorkStep extends Model
{
    protected $fillable = [
        'item_id','workflow_id','name','step_no','setup_time_sec','cycle_time_sec',
        'is_active','notes','machine_id',
    ];

    protected $casts = [
        'is_active' => 'bool',
        'cycle_time_sec' => 'float',
        'setup_time_sec' => 'float',
    ];

    public function item(): BelongsTo { return $this->belongsTo(Item::class); }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }
    
   public function machines(): BelongsToMany
    {
        return $this->belongsToMany(
            Machine::class,
            'item_work_step_machine',
            'item_work_step_id',
            'machine_id'
        )->withTimestamps();
    }

    public function capableMachines(): HasMany
    {
        // gépek, amelyek végezhetik ezt a lépést
        return $this->hasMany(ItemWorkStepMachine::class, 'item_work_step_id');
    }
}