<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class Machine extends Model
{
    protected $fillable = [
        'name','code','location','vendor','model','serial',
        'commissioned_at','active','notes','cron_enabled',
        'company_id', // <-- fontos!
    ];

    protected $casts = [
        'active'         => 'bool',
        'cron_enabled'   => 'bool',
        'commissioned_at'=> 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function devices()
    {
        return $this->hasMany(Device::class);
    }

    protected static function booted(): void
    {
        // Konzol alatt ne szűrjünk; app használat közben szűrjünk cégre
        if (!app()->runningInConsole()) {
            static::addGlobalScope('company', function (Builder $q) {
                if (Auth::check() && Auth::user()->company_id) {
                    $q->where('company_id', Auth::user()->company_id);
                }
            });
        }

        // Létrehozáskor automatikus company kitöltés
        static::creating(function (self $model) {
            if (!$model->company_id && Auth::check() && Auth::user()->company_id) {
                $model->company_id = Auth::user()->company_id;
            }
        });
    }
}
