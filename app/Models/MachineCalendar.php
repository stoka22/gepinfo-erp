<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MachineCalendar extends Model {
    protected $table = 'machine_calendars';
    public function machine() { return $this->belongsTo(Machine::class); }
}