<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class TrainingMaterial extends Model
{
    public const CATEGORIES = [
        'technologiai_tablazat' => 'Technológiai táblázat',
        'szoftver' => 'Szoftver',
    ];

    protected $fillable = [
        'title', 'description', 'kind', 'category',
        'file_path', 'file_size', 'mime_type',
        'youtube_url', 'sort_order', 'is_published',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'sort_order' => 'integer',
        'file_size' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (TrainingMaterial $r) {
            if ($r->kind !== 'file' || ! $r->file_path) {
                return;
            }

            $disk = Storage::disk('public');
            if (! $disk->exists($r->file_path)) {
                return;
            }

            if ($r->isDirty('file_path') || empty($r->file_size)) {
                $r->file_size = $disk->size($r->file_path);
                $r->mime_type = mime_content_type($disk->path($r->file_path)) ?: null;
            }
        });
    }

    public function getFileUrlAttribute(): ?string
    {
        return $this->file_path ? Storage::disk('public')->url($this->file_path) : null;
    }

    public function getFileSizeForHumansAttribute(): ?string
    {
        if (! $this->file_size) {
            return null;
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = (float) $this->file_size;
        $i = 0;
        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }

        return number_format($size, $i === 0 ? 0 : 1, ',', ' ') . ' ' . $units[$i];
    }

    /**
     * A YouTube videó azonosítója a megadott URL-ből (watch?v=, youtu.be/, embed/ formátumokból is).
     */
    public function getYoutubeIdAttribute(): ?string
    {
        if (! $this->youtube_url) {
            return null;
        }

        if (preg_match('/(?:youtu\.be\/|youtube(?:-nocookie)?\.com\/(?:watch\?v=|embed\/|shorts\/))([A-Za-z0-9_-]{11})/', $this->youtube_url, $m)) {
            return $m[1];
        }

        return null;
    }

    public function getYoutubeEmbedUrlAttribute(): ?string
    {
        $id = $this->youtube_id;

        return $id ? "https://www.youtube-nocookie.com/embed/{$id}" : null;
    }

    public function getYoutubeThumbnailAttribute(): ?string
    {
        $id = $this->youtube_id;

        return $id ? "https://img.youtube.com/vi/{$id}/hqdefault.jpg" : null;
    }
}
