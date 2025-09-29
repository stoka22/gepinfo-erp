<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Command extends Model
{
    protected $fillable = ['device_id','cmd','args','status','confirmed'];
    protected $casts = ['args' => 'array', 'confirmed' => 'boolean'];
    public function device(){ return $this->belongsTo(Device::class); }
}
