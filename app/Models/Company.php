<?php
// app/Models/Company.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    protected $fillable = ['name','group'];

    public function users(): HasMany { return $this->hasMany(User::class); }

    public function partners(): BelongsToMany {
        return $this->belongsToMany(Partner::class)->withTimestamps();
    }

    public function features(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(\App\Models\Feature::class, 'company_feature')
            ->withPivot(['enabled','value','starts_at','ends_at'])
            ->withTimestamps();
    }

    /** gyors ellenőrző */
    public function featureEnabled(string $key): bool
    {
        $f = $this->features->firstWhere('key', $key);
        if (!$f) return false;
        $now = now();
        if ($f->pivot->starts_at && $now->lt($f->pivot->starts_at)) return false;
        if ($f->pivot->ends_at   && $now->gt($f->pivot->ends_at))   return false;
        return (bool) $f->pivot->enabled;
    }

    public function skills() { return $this->hasMany(Skill::class); }

    public function positions() { return $this->hasMany(\App\Models\Position::class); }
}
