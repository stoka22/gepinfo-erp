// app/Support/FeatureGate.php
<?php

namespace App\Support;

use App\Models\Company;
use App\Models\User;

class FeatureGate
{
    protected ?Company $company = null;

    public function forUser(?User $user): static
    {
        $this->company = $user?->company ?? ($user && $user->relationLoaded('company') ? $user->company : null);
        return $this;
    }

    public function forCompany(?Company $company): static
    {
        $this->company = $company;
        return $this;
    }

    public function enabled(string $key): bool
    {
        if (!$this->company) return false;
        // ha nincs előtöltve, töltsük (cache-elhető)
        if (!$this->company->relationLoaded('features')) {
            $this->company->load('features');
        }
        return $this->company->featureEnabled($key);
    }

    public function value(string $key, mixed $default = null): mixed
    {
        if (!$this->company) return $default;
        $f = $this->company->features->firstWhere('key', $key);
        return $f?->pivot?->value ?? $default;
    }
}
