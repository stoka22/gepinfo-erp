<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResourceShiftAssignment extends Model
{
    protected $fillable = ['resource_id','shift_pattern_id','valid_from','valid_to'];

    protected $casts = [
        'valid_from' => 'date',
        'valid_to'   => 'date',
    ];

    public function resource(): BelongsTo
    {
        // "resource" = gép
        return $this->belongsTo(Machine::class, 'resource_id');
    }

    public function pattern(): BelongsTo
    {
        return $this->belongsTo(ShiftPattern::class, 'shift_pattern_id');
    }
}
