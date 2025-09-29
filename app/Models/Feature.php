<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Feature extends Model
{
    protected $fillable = ['key','name','group','description','is_enabled_default','meta'];
    protected $casts   = ['is_enabled_default'=>'bool','meta'=>'array'];

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class)
            ->withPivot(['enabled','value','starts_at','ends_at'])
            ->withTimestamps();
    }
}
