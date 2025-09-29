<?php

namespace App\Models\Pivots;
use Illuminate\Database\Eloquent\Relations\Pivot;

class EmployeeSkill extends Pivot
{
    protected $table = 'employee_skill';
    protected $casts = ['certified_at' => 'date'];

    
}