<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Device;

class DeviceAuthHelloController extends Controller
{
    public function store(Request $r): JsonResponse
    {
        /** @var Device $device */
        $device = $r->attributes->get('device');

        $data = $r->validate([
            'fw_version' => 'nullable|string|max:64',
            'ssid'       => 'nullable|string|max:64',
            'rssi'       => 'nullable|integer',
            'boot_seq'   => 'nullable|integer|min:0',
        ]);

        $wasBootIncreased = false;
        if (isset($data['boot_seq']) && (int)$data['boot_seq'] > (int)$device->boot_seq) {
            $wasBootIncreased = true;
            $device->boot_seq = (int)$data['boot_seq'];
            $device->last_boot_at = now();
        }

        $device->update([
            'fw_version'   => $data['fw_version'] ?? $device->fw_version,
            'ssid'         => $data['ssid'] ?? $device->ssid,
            'rssi'         => $data['rssi'] ?? $device->rssi,
            'last_seen_at' => now(),
            'last_ip'      => $r->ip(),
        ]);

        // Reboot parancs megerősítése, ha az utolsó 'reboot' még nem confirmed és most nőtt a boot_seq
        if ($wasBootIncreased) {
            \App\Models\Command::where('device_id', $device->id)
                ->where('cmd', 'reboot')
                ->whereIn('status', ['pending','sent','done']) // ack előtt/után
                ->latest('id')
                ->limit(1)
                ->update(['status' => 'done', 'confirmed' => true]);
        }

        return response()->json(['status' => 'ok']);
    }
}
