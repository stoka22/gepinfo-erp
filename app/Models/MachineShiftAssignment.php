<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MachineShiftAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'machine_id',
        'shift_pattern_id',
        'valid_from',
        'valid_to',
    ];

    protected $casts = [
        'valid_from' => 'date',
        'valid_to'   => 'date',
    ];

    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }

    public function shiftPattern()
    {
        return $this->belongsTo(ShiftPattern::class);
    }
}

