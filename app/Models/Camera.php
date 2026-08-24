<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class Camera extends Model
{
    protected $fillable = [
        'company_id', 'name', 'stream_url', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'bool',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    protected static function booted(): void
    {
        if (!app()->runningInConsole()) {
            static::addGlobalScope('company', function (Builder $q) {
                if (Auth::check() && Auth::user()->company_id) {
                    $q->where('company_id', Auth::user()->company_id);
                }
            });
        }

        static::creating(function (self $model) {
            if (!$model->company_id && Auth::check() && Auth::user()->company_id) {
                $model->company_id = Auth::user()->company_id;
            }
        });
    }
}
