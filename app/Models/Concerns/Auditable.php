<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

/**
 * Kritikus modellek (time_entries, vacation_balances, stb.) módosításainak naplózása:
 * ki, mikor, mit változtatott. Debug/elszámoltathatósági célra – nem üzleti logika.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            $model->writeAuditLog('created', null, $model->auditableAttributes($model->getAttributes()));
        });

        static::updated(function ($model) {
            $changes = $model->auditableAttributes($model->getChanges());
            unset($changes['updated_at']);
            if (empty($changes)) {
                return;
            }
            $original = array_intersect_key($model->getOriginal(), $changes);
            $model->writeAuditLog('updated', $original, $changes);
        });

        static::deleted(function ($model) {
            $model->writeAuditLog('deleted', $model->auditableAttributes($model->getAttributes()), null);
        });
    }

    /** Érzékeny mezők (pl. jelszó) kihagyása a naplóból; a modell $auditExcept-tel bővítheti. */
    protected function auditableAttributes(array $attributes): array
    {
        $excluded = array_merge(['password', 'remember_token'], $this->auditExcept ?? []);
        return array_diff_key($attributes, array_flip($excluded));
    }

    protected function writeAuditLog(string $event, ?array $old, ?array $new): void
    {
        AuditLog::create([
            'auditable_type' => static::class,
            'auditable_id'   => $this->getKey(),
            'event'          => $event,
            'user_id'        => Auth::id(),
            'context'        => $this->auditContext(),
            'old_values'     => $old,
            'new_values'     => $new,
            'created_at'     => now(),
        ]);
    }

    protected function auditContext(): string
    {
        if (app()->runningInConsole()) {
            return 'console:'.($_SERVER['argv'][1] ?? 'unknown');
        }

        return request()?->route()?->getName() ?? request()?->path() ?? 'web';
    }
}
