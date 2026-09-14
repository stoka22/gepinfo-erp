<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PageVisit extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'path',
        'route_name',
        'ip_hash',
        'user_agent',
        'referrer',
    ];
}
