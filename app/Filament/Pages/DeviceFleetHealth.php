<?php

namespace App\Filament\Pages;

use App\Models\Device;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class DeviceFleetHealth extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-signal';
    protected static ?string $navigationGroup = 'Eszközök';
    protected static ?string $navigationLabel = 'Flotta állapota';
    protected static ?string $title           = 'Eszközflotta állapota';
    protected static string  $view            = 'filament.pages.device-fleet-health';

    private const WEAK_RSSI_THRESHOLD = -80;

    public int $totalCount = 0;
    public int $onlineCount = 0;
    public int $offlineCount = 0;
    public int $weakSignalCount = 0;
    public array $offlineDevices = [];
    public array $firmwareDistribution = [];

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) Auth::user()?->isAdmin();
    }

    public function mount(): void
    {
        $devices = Device::query()->get();

        $this->totalCount = $devices->count();
        $this->onlineCount = $devices->filter(fn (Device $d) => $d->is_online)->count();
        $this->offlineCount = $this->totalCount - $this->onlineCount;
        $this->weakSignalCount = $devices->filter(fn (Device $d) => $d->is_online && $d->rssi !== null && $d->rssi < self::WEAK_RSSI_THRESHOLD)->count();

        $this->offlineDevices = $devices
            ->reject(fn (Device $d) => $d->is_online)
            ->sortByDesc(fn (Device $d) => $d->last_seen_age ?? PHP_INT_MAX)
            ->values()
            ->map(fn (Device $d) => [
                'name'          => $d->name,
                'location'      => $d->location,
                'last_seen_at'  => $d->last_seen_at,
                'last_seen_age' => $d->last_seen_age,
            ])
            ->all();

        $this->firmwareDistribution = $devices
            ->groupBy(fn (Device $d) => $d->fw_version ?: 'Ismeretlen')
            ->map(fn ($group, $version) => ['version' => $version, 'count' => $group->count()])
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    public function formatAge(?int $seconds): string
    {
        if ($seconds === null) {
            return 'sosem jelentkezett';
        }
        if ($seconds < 3600) {
            return round($seconds / 60).' perce';
        }
        if ($seconds < 86400) {
            return round($seconds / 3600, 1).' órája';
        }
        return round($seconds / 86400, 1).' napja';
    }
}
