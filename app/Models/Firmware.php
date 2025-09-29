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
        'version',
        'build',
        'file_path',
        'file_size',
        'mime_type',
        'sha256',
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

    public function getPublicUrlAttribute(): string
    {
        // a public/storage alól szolgáljuk ki (storage:link után)
        return Storage::url($this->file_path);
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

            $disk = Storage::disk('public');
            if (! $disk->exists($r->file_path)) {
                return;
            }

            $fullPath   = $disk->path($r->file_path);
            $needsMeta  = $r->wasChanged('file_path') || empty($r->file_size) || empty($r->mime_type) || empty($r->sha256);
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

                // SHA-256 hash közvetlenül fájlról (gyors)
                $r->sha256 = @hash_file('sha256', $fullPath) ?: null;
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
