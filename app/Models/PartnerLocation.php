<?php
// app/Models/PartnerLocation.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerLocation extends Model
{
    protected $fillable = [
        'partner_id','name','country','zip','city','street',
        'contact_name','contact_phone','contact_email',
    ];

    public function partner(): BelongsTo { return $this->belongsTo(Partner::class); }
}
