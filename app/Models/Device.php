<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Device extends Model
{
    protected $appends = ['is_online', 'last_seen_age'];

    protected $fillable = [
        'user_id','machine_id','name','mac_address','location','device_token',
        'fw_version','ssid','rssi','last_seen_at','last_ip',
        'boot_seq','last_boot_at','ota_channel','rollback_url','cron_enabled',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'last_boot_at' => 'datetime',   // <-- EZ KELL
        'rssi'         => 'integer',
        'boot_seq'     => 'integer',
        'cron_enabled' => 'bool',
    ];

    // === ONLINE LOGIKA ===
    public function getIsOnlineAttribute(): bool
    {
        $t = (int) config('devices.online_timeout', 60);
        return $this->last_seen_at && $this->last_seen_at->gt(now()->subSeconds($t));
    }

    public function getLastSeenAgeAttribute(): ?int
    {
        return $this->last_seen_at ? $this->last_seen_at->diffInSeconds(now()) : null;
    }

    public function scopeOnline($q)
    {
        $t = (int) config('devices.online_timeout', 60);
        return $q->where('last_seen_at', '>=', now()->subSeconds($t));
    }
    // Legutóbbi parancs
    public function lastCommand(): HasOne
    {
        return $this->hasOne(Command::class)->latestOfMany();
    }

    // "Aktív" parancs: amíg nincs befejezve / megerősítve
    public function activeCommand(): HasOne
    {
        return $this->hasOne(Command::class)
            ->ofMany(['id' => 'max'], function ($q) {
                $q->whereIn('status', ['pending', 'sent'])
                ->orWhere(function ($q) {
                    // reboot: done, de még nincs confirmed -> aktívnak számít
                    $q->where('cmd', 'reboot')
                        ->where('status', 'done')
                        ->where('confirmed', false);
                });
            });
    }

    protected static function booted(): void
    {
        static::creating(function (Device $d) {
            if (empty($d->device_token)) {
                $d->device_token = Str::random(48);
            }
        });
    }
    public function user(){ return $this->belongsTo(User::class); }
    public function pulses(){ return $this->hasMany(Pulse::class); }

    public function machine(){ return $this->belongsTo(Machine::class); }
    public function firmwares()   { return $this->hasMany(Firmware::class); }
    public function deviceFiles() { return $this->hasMany(DeviceFile::class); }
}
