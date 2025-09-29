<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Device;
use App\Models\Pulse;
use App\Models\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class DevicePulseController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        /** @var Device|null $device */
        $device = $request->attributes->get('device');
        if (!$device) {
            return response()->json(['error' => 'Unauthorized (device missing)'], 401, ['Connection' => 'close']);
        }

        // Opcionális mezők is jöhetnek a firmware-ből
        $data = $request->validate([
            'rssi'       => ['nullable','integer'],
            'ssid'       => ['nullable','string','max:64'],
            'sample_id'  => ['sometimes','integer','min:1'],   // ha mintát is küldesz
            'count'      => ['sometimes','integer','min:0'],
            'ts'         => ['nullable','string'],
            'boot_seq'   => ['nullable','integer'],            // ajánlott: minden bootnál nő
            'up'         => ['nullable','integer'],            // uptime másodpercben
            'uptime_ms'  => ['nullable','integer'],            // uptime ezredmásodpercben
            'fw'         => ['nullable','string','max:64'],  // <-- ÚJ
            'git'        => ['nullable','string','max:80'],
        ]);

        // ---- telemetria frissítés ----
        if (array_key_exists('rssi', $data)) $device->rssi = (int)$data['rssi'];
        if (array_key_exists('ssid', $data)) $device->ssid = $data['ssid'];

        $now = now();
        $device->last_seen_at = $now;
        $device->last_ip      = $request->ip();

        // (3) ÚJ: FW verzió (és git) mentése a pulzusból
        if (array_key_exists('fw', $data) && $data['fw'] !== null) {
            $device->fw_version = (string) $data['fw'];
        }
        if (array_key_exists('git', $data) && $data['git'] !== null
            && Schema::hasColumn($device->getTable(), 'git_sha')) {
            $device->git_sha = (string) $data['git'];
        }

        // ---- reboot detektálás (bármelyik jelből) ----
        $rebootDetected = false;

        if (isset($data['boot_seq'])) {
            $incoming = (int)$data['boot_seq'];
            if ((int)$device->boot_seq !== $incoming) {
                $device->boot_seq   = $incoming;
                $device->last_boot_at = $now;
                $rebootDetected = true;
            }
        } elseif (isset($data['up']) || isset($data['uptime_ms'])) {
            $uptimeSec = isset($data['up'])
                ? (int)$data['up']
                : (int) floor(((int)($data['uptime_ms'] ?? 0)) / 1000);
            // friss boot ~ első percben
            if ($uptimeSec >= 0 && $uptimeSec < 60) {
                $device->last_boot_at = $now;
                $rebootDetected = true;
            }
        }

        $device->save();

        // ---- AUTOMATIKUS CONFIRM reboot/reset parancsra ----
        $this->maybeConfirmReboot($device, $rebootDetected);

        // ---- (opcionális) mintavétel mentés ----
        if (isset($data['sample_id']) && isset($data['count'])) {
            $exists = Pulse::where('device_id',$device->id)
                ->where('sample_id',(int)$data['sample_id'])->exists();

            if (!$exists) {
                $sampleTime = $now;
                if (!empty($data['ts'])) {
                    try {
                        $sampleTime = is_numeric($data['ts'])
                            ? Carbon::createFromTimestamp((int)$data['ts'])
                            : Carbon::parse($data['ts']);
                    } catch (\Throwable $e) {}
                }

                $last = Pulse::where('device_id',$device->id)->orderByDesc('id')->first();
                $delta = $last ? max(0, (int)$data['count'] - (int)$last->count) : (int)$data['count'];

                Pulse::create([
                    'device_id'   => $device->id,
                    'sample_id'   => (int)$data['sample_id'],
                    'sample_time' => $sampleTime,
                    'count'       => (int)$data['count'],
                    'delta'       => $delta,
                ]);
            }
        }

        return response()->json(['ok'=>true], 200, ['Connection'=>'close']);
    }

    /**
     * A legutóbbi reboot/reset parancsot confirmed=1-re állítja,
     * ha most tért vissza a készülék (vagy a boot jelét érzékeltük).
     */
    private function maybeConfirmReboot(Device $device, bool $rebootDetected): void
    {
        $cmd = Command::where('device_id', $device->id)
            ->whereIn('cmd', ['reboot','reset'])
            ->where('status', 'done')        // ACK után 'done'-ra állítod
            ->where('confirmed', false)
            ->latest('updated_at')
            ->first();

        if (!$cmd) return;

        // Ha a firmware jelzett bootot → azonnal confirm
        if ($rebootDetected) {
            $cmd->confirmed = true;
            $cmd->save();
            return;
        }

        // Heurisztika: ha az eszköz most látott eloszor jelet a parancs után (friss visszatérés),
        // és a parancs nem régebbi 10 percnél → confirm
        if ($device->last_seen_at
            && $device->last_seen_at->gt($cmd->updated_at->copy()->addSeconds(2))
            && $cmd->updated_at->gt(now()->subMinutes(10))) {

            $cmd->confirmed = true;
            $cmd->save();
        }
    }
}
