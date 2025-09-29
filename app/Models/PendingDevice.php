<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PendingDevice extends Model {
    protected $fillable = ['mac_address','proposed_name','fw_version','ip','last_seen_at'];
}
