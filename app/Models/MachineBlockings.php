<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MachineBlocking extends Model
{
    use HasFactory;

    protected $fillable = [
        'machine_id',
        'starts_at',
        'ends_at',
        'reason',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at'   => 'datetime',
    ];

    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }
}
