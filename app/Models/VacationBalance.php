<?php
// app/Models/VacationBalance.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VacationBalance extends Model
{
    protected $fillable = [
        'employee_id','year','company_id',
        'base_days','age_extra_days',
        'carried_over_days','manual_adjustment_days',
    ];

    protected $casts = [
        'year' => 'integer',
        'base_days' => 'decimal:1',
        'age_extra_days' => 'decimal:1',
        'carried_over_days' => 'decimal:1',
        'manual_adjustment_days' => 'decimal:1',
    ];

    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }

    /** Pótszabik összege az adott évre a vacation_allowances táblából */
    public function getAllowanceDaysAttribute(): float
    {
        return (float) VacationAllowance::query()
            ->where('employee_id', $this->employee_id)
            ->where('year', $this->year)
            ->sum('days');
    }

    /** Jogosultság összesen = alapszabi + életkor + típusonként rögzített pótszabik + hozott + kézi korrekció */
    public function getEntitledDaysAttribute(): float
    {
        return (float) (
            $this->base_days
            + $this->age_extra_days
            + $this->allowance_days
            + $this->carried_over_days
            + $this->manual_adjustment_days
        );
    }

    /** Felhasznált napok – maradhat a korábbi logikád */
    public function getUsedDaysAttribute(): float
    {
        return (float) \App\Models\TimeEntry::query()
            ->where('employee_id', $this->employee_id)
            ->whereYear('start_date', $this->year)
            ->where('type', \App\Enums\TimeEntryType::Vacation)
            ->where('status', \App\Enums\TimeEntryStatus::Approved)
            ->get()
            ->sum(fn ($r) => \App\Models\TimeEntry::countBusinessDaysForEntry($r));
    }

    public function getRemainingDaysAttribute(): float
    {
        return max(0, $this->entitled_days - $this->used_days);
    }
}
