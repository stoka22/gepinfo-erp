<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkLog extends Model
{
    use HasFactory;

    protected $table = 'work_logs';


    protected $fillable = [
        'nev',
        'munkakor',
        'helyiseg',
        'belepesi_pont',
        'kezdes',
        'kilepesi_pont',
        'vege',
        'ido',
        'employee_id',
        'is_archived'
    ];


    protected $casts = [
        'kezdes' => 'datetime',
        'vege'   => 'datetime',
    ];


    public function employee()
    {
        return $this->belongsTo(\App\Models\Employee::class, 'employee_id');
    }
}
