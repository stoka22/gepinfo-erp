<?php

namespace App\Filament\Pages;

use App\Models\Camera;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class CameraDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-video-camera';
    protected static ?string $navigationGroup = 'Eszközök';
    protected static ?string $navigationLabel = 'Kamerák';
    protected static ?string $title = 'Élő kamerakép';
    protected static string $view = 'filament.pages.camera-dashboard';

    public array $cameras = [];

    public static function shouldRegisterNavigation(): bool
    {
        return Auth::check() && Camera::query()->where('is_active', true)->exists();
    }

    public function mount(): void
    {
        $this->cameras = Camera::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Camera $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'stream_url' => $c->stream_url,
            ])
            ->all();
    }
}
