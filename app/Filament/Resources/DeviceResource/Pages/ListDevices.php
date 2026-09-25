<?php

namespace App\Filament\Resources\DeviceResource\Pages;

use App\Filament\Resources\DeviceResource;
use App\Models\Command;
use App\Models\Device;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Collection;

/**
 * Egyedi, nem a Filament tábla-komponensét használó lista-nézet -- a
 * felhasználó kérésére az Energy projekt "Mérők" oldalának (resources/
 * views/energy/meters.blade.php) vizuális stílusát követi (sötét kártyák,
 * széles, vízszintesen görgethető táblázat, badge-pill állapotjelzők),
 * mert a Filament alapértelmezett táblázat-komponense (Split/Stack layout
 * vagy akár sima oszlopok) élesben nem adott professzionális megjelenést.
 *
 * A szerkesztés/csatorna-gép hozzárendelés/WiFi hálózatok/firmware-cél
 * TOVÁBBRA IS a meglévő Filament EditDevice oldalon történik (a
 * "Szerkesztés" gomb csak odairányít) -- itt csak a lista-nézet és a már
 * meglévő gyors műveletek (reboot/factory_reset/cron/törlés) kerülnek újra
 * megépítésre, Livewire action-ökkel az Energy oldal vanilla JS/fetch
 * mintája helyett.
 */
class ListDevices extends ListRecords
{
    protected static string $resource = DeviceResource::class;

    protected static string $view = 'filament.resources.device-resource.pages.list-devices';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getDevices(): Collection
    {
        return Device::query()
            ->with(['machines', 'activeCommand'])
            ->orderByDesc('last_seen_at')
            ->get();
    }

    public function reboot(int $deviceId): void
    {
        Command::create(['device_id' => $deviceId, 'cmd' => 'reboot', 'status' => 'pending']);

        Notification::make()->title('Újraindítás parancs elküldve')->success()->send();
    }

    public function factoryReset(int $deviceId): void
    {
        Command::create(['device_id' => $deviceId, 'cmd' => 'factory_reset', 'status' => 'pending']);

        Notification::make()->title('Factory reset parancs elküldve')->success()->send();
    }

    public function stopCommands(int $deviceId): void
    {
        $count = Command::where('device_id', $deviceId)
            ->where('status', 'pending')
            ->update(['status' => 'cancelled']);

        Notification::make()
            ->title('Parancsok leállítva')
            ->body("{$count} függőben lévő parancs leállítva.")
            ->success()
            ->send();
    }

    public function deleteDevice(int $deviceId): void
    {
        Device::findOrFail($deviceId)->delete();

        Notification::make()->title('Eszköz törölve')->success()->send();
    }
}
