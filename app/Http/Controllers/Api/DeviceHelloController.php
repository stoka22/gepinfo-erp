<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Device;
use App\Models\PendingDevice;

class DeviceHelloController extends Controller
{
    public function store(Request $r)
    {
        // Fogadjuk a firmware kulcsait (fw vagy fw_version is jó), plusz opcionális mezők
        $data = $r->validate([
            'mac'         => 'required|string|max:32',
            'name'        => 'nullable|string|max:100',
            'fw'          => 'nullable|string|max:64',
            'fw_version'  => 'nullable|string|max:64',
            'ssid'        => 'nullable|string|max:64',
            'rssi'        => 'nullable|integer',
            'boot_seq'    => 'nullable|integer',
        ]);

        $mac = strtoupper($data['mac']);
        $fw  = $data['fw_version'] ?? $data['fw'] ?? null;

        // Jóváhagyott eszköz létezik? -> token kiosztás / frissítés
        $device = Device::where('mac_address', $mac)->first();

        if ($device) {
            // ha nincs token, generáljunk
            if (empty($device->device_token)) {
                $device->device_token = Str::random(48);
            }

            // frissítések (csak ha érkezett adat)
            if (!empty($data['name']))            $device->name        = $data['name'];
            if ($fw !== null)                     $device->fw_version  = $fw;
            if (array_key_exists('ssid', $data))  $device->ssid        = $data['ssid'];
            if (array_key_exists('rssi', $data))  $device->rssi        = $data['rssi'];

            if (array_key_exists('boot_seq', $data)) {
                $incoming = (int)$data['boot_seq'];
                // ha változott a boot szekvencia, jelöljük az utolsó boot időpontot
                if ($device->boot_seq !== $incoming) {
                    $device->boot_seq   = $incoming;
                    $device->last_boot_at = now();
                }
            }

            $device->last_seen_at = now();
            $device->last_ip      = $r->ip();
            $device->save();

            // ESP elvárás szerinti válasz
            return response()->json([
                'provision'    => true,
                'device_token' => $device->device_token,
            ], 200, ['Connection' => 'close']);
        }

        // Nincs még eszköz -> várólistára tesszük (PendingDevice)
        PendingDevice::updateOrCreate(
            ['mac_address' => $mac],
            [
                'proposed_name' => $data['name'] ?? null,
                'fw_version'    => $fw,
                'ip'            => $r->ip(),
                'last_seen_at'  => now(),
            ]
        );

        // Amíg nincs jóváhagyva, jelezzük az ESP-nek, hogy még nincs token
        return response()->json([
            'provision' => false,
            'status'    => 'pending',
        ], 202, ['Connection' => 'close']);
    }
}
