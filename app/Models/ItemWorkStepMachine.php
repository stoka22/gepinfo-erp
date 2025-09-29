<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemWorkStepMachine extends Model
{
    protected $table = 'item_work_step_machine';

    protected $fillable = [
        'item_work_step_id','machine_id',
    ];

    public function step(): BelongsTo
    {
        return $this->belongsTo(ItemWorkStep::class, 'item_work_step_id');
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }
}
