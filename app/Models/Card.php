<?php
// app/Models/Card.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Card extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uid', 'label', 'status', 'employee_id', 'assigned_at', 'notes',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
       
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function scopeAvailable($q)
    {
        return $q->whereNull('employee_id')->where('status', 'available');
    }
}
