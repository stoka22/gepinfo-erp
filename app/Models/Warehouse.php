<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Warehouse extends Model
{
    protected $fillable = ['company_id','code','name','location'];

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
}
