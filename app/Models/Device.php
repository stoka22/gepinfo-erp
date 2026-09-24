<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class Device extends Model
{
    protected $appends = ['is_online', 'last_seen_age'];

    protected $fillable = [
        'user_id','machine_id','name','mac_address','location',
        'fw_version','ssid','rssi','last_seen_at','last_ip',
        'boot_seq','last_boot_at','ota_channel','rollback_url','cron_enabled',
        'meta','api_key_hash','platform',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'last_boot_at' => 'datetime',   // <-- EZ KELL
        'rssi'         => 'integer',
        'boot_seq'     => 'integer',
        'cron_enabled' => 'bool',
        'meta'         => 'array',
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

    /**
     * Bcrypt ellenőrzés az enroll/push kontraktban kiadott API-kulcs ellen
     * (App\Http\Controllers\Api\DeviceEnrollmentController). Az Energy projekt
     * Meter::apiKeyMatches()-ének literál mintája.
     */
    public function apiKeyMatches(?string $plainKey): bool
    {
        return $plainKey && $this->api_key_hash && Hash::check($plainKey, $this->api_key_hash);
    }

    public function machineForChannel(int $channel): ?Machine
    {
        return $this->channels->firstWhere('channel', $channel)?->machine;
    }

    /**
     * A firmware a saját chip-MAC-jából származtatott "ESP32_<HEX>" /
     * "ESP8266_<HEX>" alakú device_id-t küld enrollmentkor és minden push-nál
     * (lásd DeviceEnrollmentController::DEVICE_ID_PATTERN). Nincs külön
     * device_uid oszlop -- ezt normalizáljuk a devices.mac_address mezőben
     * már megszokott, admin-barát "AA:BB:CC:DD:EE:FF" alakra, hogy az admin
     * felület MAC mezője ne kelljen a firmware nyers formátumát tükrözze.
     * Visszaad null-t, ha a device_id nem illeszkedik a várt mintára.
     */
    public static function normalizeDeviceId(string $deviceId): ?string
    {
        if (! preg_match('/^(?:ESP32|ESP8266)_([0-9A-Fa-f]{12})$/', $deviceId, $m)) {
            return null;
        }

        return strtoupper(implode(':', str_split($m[1], 2)));
    }

    public static function platformFromDeviceId(string $deviceId): ?string
    {
        return match (true) {
            str_starts_with($deviceId, 'ESP32_') => 'esp32',
            str_starts_with($deviceId, 'ESP8266_') => 'esp8266',
            default => null,
        };
    }

    /**
     * Az inverze normalizeDeviceId()-nek: visszaállítja a firmware saját
     * maga által küldött "ESP32_<HEX>"/"ESP8266_<HEX>" alakot a tárolt
     * mac_address + platform párból. A firmware-letöltési URL-be kell
     * (device_id query paraméter), mert a DeviceApiKeyMiddleware ez alapján
     * azonosítja a kérő eszközt.
     */
    public function deviceIdString(): ?string
    {
        if (! $this->platform) {
            return null;
        }

        return strtoupper($this->platform).'_'.str_replace(':', '', strtoupper($this->mac_address));
    }

    protected static function booted(): void
    {
        // Minden eszköznek mindig pontosan 4 szerkeszthető csatornája legyen
        // (d1..d4), mert egy panel akár 4 különálló gépet is kiszolgálhat.
        static::created(function (Device $d) {
            foreach ([1, 2, 3, 4] as $channel) {
                $d->channels()->create(['channel' => $channel]);
            }
        });
    }

    public function user(){ return $this->belongsTo(User::class); }
    public function pulses(){ return $this->hasMany(Pulse::class); }

    // Megtartva visszafelé kompatibilitás miatt -- az új rendszerben a
    // csatorna->gép hozzárendelést a channels()/device_channels végzi.
    public function machine(){ return $this->belongsTo(Machine::class); }

    public function firmwares()   { return $this->hasMany(Firmware::class); }
    public function deviceFiles() { return $this->hasMany(DeviceFile::class); }

    public function channels(): HasMany
    {
        return $this->hasMany(DeviceChannel::class);
    }

    public function machines(): BelongsToMany
    {
        return $this->belongsToMany(Machine::class, 'device_channels')
            ->withPivot('channel', 'label', 'active')
            ->distinct();
    }
}
