<?php
// app/Models/VacationAllowance.php
namespace App\Models;

use App\Enums\VacationAllowanceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VacationAllowance extends Model
{
    protected $fillable = [
        'employee_id','year','type','days','note','company_id',
    ];

    protected $casts = [
        'year' => 'integer',
        'type' => VacationAllowanceType::class,
        'days' => 'decimal:1',
    ];

    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
}
