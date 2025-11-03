<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeCard extends Model
{
    use HasFactory;
    protected $table = 'employee_cards';

    protected $fillable = [
        'employee_id',
        'card_uid',
        'label',
        'type',
        'active',
        'assigned_at',
    ];

    protected $casts = [
        'active' => 'bool',
        'assigned_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
