<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TerminalWebhookFailure extends Model
{
    public $timestamps = false; // csak created_at van, immutable napló

    protected $fillable = [
        'error_code', 'http_status', 'card_uid', 'direction', 'message', 'payload', 'ip_address', 'created_at',
    ];

    protected $casts = [
        'payload'    => 'array',
        'created_at' => 'datetime',
    ];
}
