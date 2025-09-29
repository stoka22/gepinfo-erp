<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftBreak extends Model
{
    protected $fillable = ['shift_pattern_id','name','start_time','duration_min'];

    public function pattern(): BelongsTo
    {
        return $this->belongsTo(ShiftPattern::class, 'shift_pattern_id');
    }
}
