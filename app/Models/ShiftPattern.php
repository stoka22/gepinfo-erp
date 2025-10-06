<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShiftPattern extends Model
{
    protected $fillable = ['name', 'start_time', 'end_time', 'days_mask']; // ← add ide!
    protected $casts = ['days_mask' => 'integer'];

    public static function dayMap(): array {
        // 1<<0=1=H, 1<<1=2=K, … 1<<6=64=V
        return [1=>'H', 2=>'K', 4=>'Sze', 8=>'Cs', 16=>'P', 32=>'Szo', 64=>'V'];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ResourceShiftAssignment::class);
    }

    public function breaks(): HasMany
    {
        return $this->hasMany(ShiftBreak::class, 'shift_pattern_id')->orderBy('start_time');
    }

     /** @return array<int> pl. [1,2,3,4,5] H–P */
    public function getDaysAttribute(): array
    {
        $out = [];
        for ($i = 0; $i <= 6; $i++) {
            if (($this->days_mask & (1 << $i)) !== 0) $out[] = $i;
        }
        return $out;
    }

    /** @param array<int> $days */
    public function setDaysAttribute(array $days): void
    {
        $mask = 0;
        foreach ($days as $d) {
            $d = (int)$d;
            if ($d >=0 && $d <=6) $mask |= (1 << $d);
        }
        $this->attributes['days_mask'] = $mask;
    }

    /** gyors ellenőrzés: adott napra vonatkozik-e */
    public function appliesToDow(int $dow): bool
    {
        return ($this->days_mask & (1 << $dow)) !== 0;
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function getDaysLabelAttribute(): string {
        $labels = [];
        foreach (self::dayMap() as $bit => $label) {
            if (($this->days_mask & $bit) === $bit) $labels[] = $label;
        }
        return $labels ? implode(', ', $labels) : '—';
    }
}
