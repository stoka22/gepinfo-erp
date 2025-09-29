<?php

// app/Models/TaskDependency.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskDependency extends Model
{
    protected $fillable = [
        'predecessor_id','successor_id','type','lag_minutes',
    ];

    public function predecessor(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'predecessor_id');
    }

    public function successor(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'successor_id');
    }
}

