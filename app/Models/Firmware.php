<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;

class Firmware extends Model
{
    protected $table = 'firmwares';

    protected $fillable = [
        'device_id',
        'hardware_code',
        'platform',
        'version',
        'build',
        'file_path',
        'file_size',
        'mime_type',
        'sha256',
        'md5',
        'forced',
        'published_at',
        'notes',
    ];

    protected $casts = [
        'forced'       => 'bool',
        'published_at' => 'datetime',
    ];

    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * Mentés után töltsük ki/ frissítsük a fájl metaadatait és a published_at-ot.
     */
    protected static function booted(): void
    {
        static::saved(function (Firmware $r): void {
            if (! $r->file_path) {
                return;
            }

            $disk = Storage::disk('local');
            if (! $disk->exists($r->file_path)) {
                return;
            }

            $fullPath   = $disk->path($r->file_path);
            $needsMeta  = $r->wasChanged('file_path') || empty($r->file_size) || empty($r->mime_type) || empty($r->sha256) || empty($r->md5);
            $needsDate  = empty($r->published_at) || $r->wasChanged('file_path');

            if (! $needsMeta && ! $needsDate) {
                return;
            }

            if ($needsMeta) {
                // Méret
                $r->file_size = $disk->size($r->file_path);

                // MIME (Laravel metódus + fallback)
                try {
                    $r->mime_type = $disk->mimeType($r->file_path);
                } catch (\Throwable $e) {
                    $r->mime_type = @mime_content_type($fullPath) ?: null;
                }

                // SHA-256 + MD5 hash közvetlenül fájlról. Az MD5-öt az OTA
                // letöltési endpoint küldi az `x-MD5` fejlécben -- az ESP32
                // HTTPUpdate library ez alapján ellenőrzi a letöltött bájtokat,
                // sosem a kliens által megadott hash-ből (ami itt nem is kap
                // szerepet, mindig szerver-oldalon, feltöltéskor számolunk).
                $r->sha256 = @hash_file('sha256', $fullPath) ?: null;
                $r->md5    = @md5_file($fullPath) ?: null;
            }

            if ($needsDate) {
                // Fájlrendszer szerinti módosítási idő → published_at
                try {
                    $ts = $disk->lastModified($r->file_path); // UNIX timestamp
                    if ($ts) {
                        $r->published_at = Carbon::createFromTimestamp($ts);
                    }
                } catch (\Throwable $e) {
                    // ignoráljuk, ha nem olvasható
                }
            }

            // Csendes mentés: nem triggeli újra az eseményeket
            $r->saveQuietly();
        });
    }
}
