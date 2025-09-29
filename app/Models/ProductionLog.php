<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionLog extends Model
{
    public $timestamps = false;           // csak created_at-ot írunk
    protected $table = 'production_logs';

    protected $fillable = ['machine_id','qty','created_at'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }
}
