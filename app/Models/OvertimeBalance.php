<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OvertimeBalance extends Model
{
    use Auditable;

    protected $fillable = [
        'employee_id',
        'company_id',
        'balance_minutes',
        'manual_adjustment_minutes',
    ];

    protected $casts = [
        'balance_minutes' => 'integer',
        'manual_adjustment_minutes' => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function getEffectiveBalanceMinutesAttribute(): int
    {
        return $this->balance_minutes + $this->manual_adjustment_minutes;
    }
}
