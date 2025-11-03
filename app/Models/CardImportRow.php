<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardImportRow extends Model
{
    protected $fillable = [
        'card_import_id',
        'name',
        'rfid',
        'company_id',          // ha mode == by_group, itt tároljuk a sor cégét
        'matched_employee_id', // csak AKTÍV dolgozó mehet ide
        'match_confidence',    // 0..100
        'status',              // new|auto|linked|skipped|error
        'error_msg',
    ];

    protected $casts = [
        'match_confidence' => 'int',
    ];

    public function import(): BelongsTo
    {
        return $this->belongsTo(CardImport::class, 'card_import_id');
    }

    public function matchedEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'matched_employee_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
