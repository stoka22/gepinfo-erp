<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pulse extends Model
{
    protected $fillable = ['device_id','sample_id','sample_time','count','delta'];

    public function device(){
        return $this->belongsTo(Device::class);
    }
}
