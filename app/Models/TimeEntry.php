<?php

namespace App\Models;

use App\Enums\TimeEntryStatus;
use App\Enums\TimeEntryType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class TimeEntry extends Model
{
    protected $fillable = [
        'employee_id', 'type', 'status',
        'start_date', 'end_date', 'hours',
        'note', 'requested_by', 'approved_by',
        'company_id',
    ];

    protected $casts = [
        'type'       => TimeEntryType::class,
        'status'     => TimeEntryStatus::class,
        'start_date' => 'date',
        'end_date'   => 'date',
        'hours'      => 'decimal:2',
    ];

    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }

    public function isAbsence(): bool
    {
        return in_array($this->type, [TimeEntryType::Vacation, TimeEntryType::SickLeave], true);
    }

    protected static function booted(): void
    {
        // Globális cég-scope app módban
        if (!app()->runningInConsole()) {
            static::addGlobalScope('company', function (Builder $q) {
                if (Auth::check() && Auth::user()->company_id) {
                    $q->where('company_id', Auth::user()->company_id);
                }
            });
        }

        // Létrehozás: cég automatikus beállítása
        static::creating(function (self $model) {
            if (!$model->company_id) {
                if ($model->employee_id) {
                    $model->company_id = self::companyIdForEmployee((int)$model->employee_id)
                        ?? Auth::user()?->company_id;
                } else {
                    $model->company_id = Auth::user()?->company_id;
                }
            }
        });

        // Mentés előtt: ha van employee, vele tegyük konzisztenssé
        static::saving(function (self $model) {
            if ($model->employee_id) {
                $empCid = self::companyIdForEmployee((int)$model->employee_id);
                if ($empCid && $empCid !== $model->company_id) {
                    $model->company_id = $empCid;
                }
            }
        });
    }

    // ---- Helper: employee -> company_id, fallback users joinra, ha nincs employees.company_id
    protected static function companyIdForEmployee(int $employeeId): ?int
    {
        if (Schema::hasColumn('employees', 'company_id')) {
            $val = Employee::query()->whereKey($employeeId)->value('company_id');
            return $val !== null ? (int)$val : null;
        }

        if (Schema::hasColumn('employees', 'user_id') && Schema::hasColumn('users', 'company_id')) {
            $val = DB::table('employees')
                ->join('users', 'users.id', '=', 'employees.user_id')
                ->where('employees.id', $employeeId)
                ->value('users.company_id');
            return $val !== null ? (int)$val : null;
        }

        return null;
    }

    // Kényelmi scope
    public function scopeForCompany(Builder $q, int $companyId): Builder
    {
        return $q->where('company_id', $companyId);
    }

    public static function countBusinessDaysForEntry(self $entry): float
    {
        if ($entry->type !== TimeEntryType::Vacation) {
            return 0.0;
        }

        // Túlóra órában jön – nem szabadságnap
        if (!empty($entry->hours)) {
            return 0.0;
        }

        $start = Carbon::parse($entry->start_date);
        $end   = Carbon::parse($entry->end_date ?? $entry->start_date);

        if ($end->lt($start)) {
            [$start, $end] = [$end, $start];
        }

        $days = 0;
        $d = $start->copy();
        while ($d->lte($end)) {
            if (!$d->isWeekend()) {
                $days += 1;
            }
            $d->addDay();
        }

        return (float) $days;
    }
}
