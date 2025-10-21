<?php

namespace App\Models;

use App\Enums\Shift;
use App\Models\Pivots\EmployeeSkill;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Builder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;

class Employee extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'email',
        'phone',
        'position_id',
        'hired_at',
        'shift',
        'birth_date',
        'children_under_16',
        'is_disabled',
        'company_id',
        'created_by_user_id',
        'account_user_id',
    ];

    protected function casts(): array
    {
        return [
            'hired_at'          => 'date',
            'birth_date'        => 'date',
            'shift'             => Shift::class,
            'children_under_16' => 'integer',
            'is_disabled'       => 'boolean',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function position(): BelongsTo { return $this->belongsTo(Position::class); }

    public function skills(): BelongsToMany
    {
        $cid = Auth::user()->company_id ?? null; // ← Filament nélkül

        $rel = $this->belongsToMany(Skill::class, 'employee_skill')
            ->using(EmployeeSkill::class)                 // ← saját pivot (dátum cast)
            ->withPivot(['level', 'certified_at', 'notes'])
            ->withTimestamps();

        if ($cid && Schema::hasColumn('skills', 'company_id')) {
            $rel->where('skills.company_id', $cid);
        }

        $rel->tap(function (Builder $q) {
            $q->getQuery()->orders = null;
        })->orderBy('skills.name', 'asc');

        return $rel;
    }

    public function workflows(): BelongsToMany
    {
        return $this->belongsToMany(Workflow::class)->withTimestamps();
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    public function vacationAllowances(): HasMany
    {
        return $this->hasMany(VacationAllowance::class);
    }
    public function shiftPattern()
    {
        return $this->belongsTo(\App\Models\ShiftPattern::class, 'shift_pattern_id');
    }

    public function company()        { return $this->belongsTo(Company::class); }
    public function companies() {
        return $this->belongsToMany(Company::class, 'employee_company_memberships')
            ->withPivot(['starts_on','ends_on','active','role'])->withTimestamps();
    }
    public function creator()        { return $this->belongsTo(User::class, 'created_by_user_id'); }
    public function accountUser()    { return $this->belongsTo(User::class, 'account_user_id'); }
    public function scopeActiveInCompany(Builder $q, int $companyId) {
        return $q->whereHas('companies', fn($c)=>$c->where('company_id',$companyId)
            ->where('active',true)
            ->where(function($w){
                $w->whereNull('starts_on')->orWhere('starts_on','<=',today());
            })->where(function($w){
                $w->whereNull('ends_on')->orWhere('ends_on','>=',today());
            })
        );
    }
   
}
