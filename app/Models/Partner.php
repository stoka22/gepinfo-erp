<?php
// app/Models/Partner.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Partner extends Model
{
    protected $fillable = [
        'name','tax_id','owner_company_id','is_supplier','is_customer',
    ];

    protected $casts = [
        'is_supplier' => 'bool',
        'is_customer' => 'bool',
    ];

    public function ownerCompany(): BelongsTo {
        return $this->belongsTo(Company::class, 'owner_company_id');
    }

    public function companies(): BelongsToMany {
        //return $this->belongsToMany(Company::class)->withTimestamps();
        return $this->belongsToMany(\App\Models\Company::class, 'company_partner'); // pivot neve nálatok
    }

    public function locations(): HasMany {
        return $this->hasMany(PartnerLocation::class);
    }

    /** Csak a megadott céghez rendelt partnerek (pivot alapján) */
    public function scopeForCompany(Builder $q, ?Company $company): Builder
    {
        if (!$company) return $q->whereRaw('1=0');
        return $q->whereHas('companies', fn($qq)=>$qq->where('companies.id', $company->id));
    }
    public function orders(): HasMany
    {
        // ha a tábla neve partner_orders és a FK partner_id, ez jó
        return $this->hasMany(PartnerOrder::class, 'partner_id');
    }
    
}
