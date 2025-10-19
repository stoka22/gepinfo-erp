<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pulse extends Model
{
    
    protected $fillable = [
    'device_id','sample_time',
    'd1_delta','d2_delta','d3_delta','d4_delta',
    'd1_total','d2_total','d3_total','d4_total',
    // (kompat) 'sample_id','count','delta'
    ];

    public function device(){
        return $this->belongsTo(Device::class);
    }
}
