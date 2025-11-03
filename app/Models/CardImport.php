<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardImport extends Model
{
    protected $fillable = [
        'file_path',
        'mode',              // 'single_company' | 'by_group'
        'company_id',        // ha single_company
        'company_group_id',  // ha by_group
        'total_rows',
        'status_rows_auto',
        'status_rows_linked',
        'status_rows_skipped',
        'status_rows_error',
    ];

    protected $casts = [
        'meta'        => 'array',
        'match_score' => 'float',
        'total_rows'          => 'int',
        'status_rows_auto'    => 'int',
        'status_rows_linked'  => 'int',
        'status_rows_skipped' => 'int',
        'status_rows_error'   => 'int',
    ];

    public function rows(): HasMany
    {
        return $this->hasMany(CardImportRow::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function companyGroup(): BelongsTo
    {
        return $this->belongsTo(CompanyGroup::class, 'company_group_id');
    }
}
