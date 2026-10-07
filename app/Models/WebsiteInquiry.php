<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebsiteInquiry extends Model
{
    protected $fillable = [
        'cegnev', 'kapcsolattarto_neve', 'telefon', 'email', 'answers', 'ip_address',
    ];

    protected $casts = [
        'answers' => 'array',
    ];
}
