<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class Skill extends Model
{
    protected $fillable = ['name', 'category', 'description', 'company_id'];

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class)
            ->withPivot(['level', 'certified_at', 'notes'])
            ->withTimestamps();
    }

    public function workflows(): BelongsToMany
    {
        return $this->belongsToMany(Workflow::class, 'workflow_skill')
            ->withPivot(['required_level'])
            ->withTimestamps();
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    protected static function booted(): void
    {
        // Konzol alatt (migrációk, seeding) ne tegyünk rá globális scope-ot
        if (!app()->runningInConsole()) {
            static::addGlobalScope('company', function (Builder $q) {
                if (Auth::check() && Auth::user()->company_id) {
                    $q->where('company_id', Auth::user()->company_id);
                }
            });
        }

        // Új rekord létrehozásakor automatikusan állítsuk a company_id-t
        static::creating(function (self $model) {
            if (!$model->company_id && Auth::check() && Auth::user()->company_id) {
                $model->company_id = Auth::user()->company_id;
            }
        });
    }
}
