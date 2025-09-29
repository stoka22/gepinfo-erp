<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class DeviceFile extends Model
{
    protected $fillable = [
        'device_id','title','kind','file_path','file_size','mime_type',
    ];

    protected static function booted(): void
    {
        static::saved(function (DeviceFile $r) {
            if (! $r->file_path) {
                return;
            }

            // public diszkre töltünk (FileUpload::disk('public'))
            $disk = Storage::disk('public');

            if (! $disk->exists($r->file_path)) {
                return;
            }

            // csak akkor számoljunk újra, ha változott a fájl vagy még üresek a mezők
            if ($r->wasChanged('file_path') || empty($r->file_size) || empty($r->mime_type)) {
                $fullPath      = $disk->path($r->file_path);
                $r->file_size  = $disk->size($r->file_path);
                // IDE néha aláhúzza, de ez létező Laravel metódus:
                //$r->mime_type  = $disk->mimeType($r->file_path);
                $r->mime_type = mime_content_type(Storage::disk('public')->path($r->file_path));
                // hatékonyabb mint Storage::get(): fájlrendszerről hashelünk
                $r->sha256     = @hash_file('sha256', $fullPath) ?: null;

                $r->saveQuietly();
            }
        });
    }

    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    public function getPublicUrlAttribute(): string
    {
        return Storage::url($this->file_path);
    }
}
