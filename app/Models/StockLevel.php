<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockLevel extends Model
{
    protected $fillable = ['company_id','warehouse_id','item_id','qty','avg_cost'];
    protected $casts = ['qty'=>'decimal:3','avg_cost'=>'decimal:4'];
}
