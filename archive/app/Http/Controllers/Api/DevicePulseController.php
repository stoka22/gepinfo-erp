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
use Illuminate\Support\Facades\Log;

class DevicePulseController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        /** @var Device|null $device */
        $device = $request->attributes->get('device');
        if (!$device) {
            return response()->json(['error'=>'Unauthorized (device missing)'], 401, ['Connection'=>'close']);
        }

        $data = $request->validate([
            'rssi'      => ['nullable','integer'],
            'ssid'      => ['nullable','string','max:64'],
            'ts'        => ['nullable','string'],
            'up'        => ['nullable','integer'],
            'uptime_ms' => ['nullable','integer'],
            'boot_seq'  => ['nullable','integer'],
            'fw'        => ['nullable','string','max:64'],
            'git'       => ['nullable','string','max:80'],

            'pulses'           => ['sometimes','array'],
            'pulses.d1'        => ['nullable','integer','min:0'],
            'pulses.d2'        => ['nullable','integer','min:0'],
            'pulses.d3'        => ['nullable','integer','min:0'],
            'pulses.d4'        => ['nullable','integer','min:0'],
            'pulses_total'     => ['sometimes','array'],
            'pulses_total.d1'  => ['nullable','integer','min:0'],
            'pulses_total.d2'  => ['nullable','integer','min:0'],
            'pulses_total.d3'  => ['nullable','integer','min:0'],
            'pulses_total.d4'  => ['nullable','integer','min:0'],

            // kompat régi mezők (nem kötelező)
            'sample_id'        => ['sometimes','integer','min:1'],
            'count'            => ['sometimes','integer','min:0'],
        ]);

        // Telemetria
        if (array_key_exists('rssi',$data)) $device->rssi = (int)$data['rssi'];
        if (array_key_exists('ssid',$data)) $device->ssid = $data['ssid'];
        $device->last_seen_at = now();
        $device->last_ip      = $request->ip();
        if (array_key_exists('fw',$data) && $data['fw'] !== null) $device->fw_version = (string)$data['fw'];
        if (array_key_exists('git',$data) && $data['git'] !== null
            && Schema::hasColumn($device->getTable(),'git_sha')) {
            $device->git_sha = (string)$data['git'];
        }

        // Reboot detektálás
        $rebootDetected = false;
        if (isset($data['boot_seq'])) {
            $incoming = (int)$data['boot_seq'];
            if ((int)$device->boot_seq !== $incoming) {
                $device->boot_seq    = $incoming;
                $device->last_boot_at= now();
                $rebootDetected = true;
            }
        } elseif (isset($data['up']) || isset($data['uptime_ms'])) {
            $uptimeSec = isset($data['up'])
                ? (int)$data['up']
                : (int) floor(((int)($data['uptime_ms'] ?? 0)) / 1000);
            if ($uptimeSec >= 0 && $uptimeSec < 60) {
                $device->last_boot_at = now();
                $rebootDetected = true;
            }
        }
        $device->save();
        $this->maybeConfirmReboot($device, $rebootDetected);

        // Mintavételi idő => perc bucket
        $sampleTime = now();
        if (!empty($data['ts'])) {
            try {
                $sampleTime = is_numeric($data['ts'])
                    ? Carbon::createFromTimestampMs((int)$data['ts'])
                    : Carbon::parse($data['ts']);
            } catch (\Throwable $e) { /* ignore */ }
        }
        $bucket = $sampleTime->copy()->seconds(0)->microseconds(0);

        // Ha nincs többcsatornás adat: küldjünk 0-kat is heartbeatként
        $pulses = $data['pulses'] ?? [];
        $totals = $data['pulses_total'] ?? [];
        $d = [
            'd1' => (int)($pulses['d1'] ?? 0),
            'd2' => (int)($pulses['d2'] ?? 0),
            'd3' => (int)($pulses['d3'] ?? 0),
            'd4' => (int)($pulses['d4'] ?? 0),
        ];
        $tFromFw = [
            'd1' => isset($totals['d1']) ? (int)$totals['d1'] : null,
            'd2' => isset($totals['d2']) ? (int)$totals['d2'] : null,
            'd3' => isset($totals['d3']) ? (int)$totals['d3'] : null,
            'd4' => isset($totals['d4']) ? (int)$totals['d4'] : null,
        ];

        // Előző állapot lekérése
        $prev = Pulse::where('device_id',$device->id)
            ->where('sample_time','<',$bucket)
            ->orderByDesc('sample_time')->first();
        $prevTotal = [
            'd1' => $prev ? (int)$prev->d1_total : 0,
            'd2' => $prev ? (int)$prev->d2_total : 0,
            'd3' => $prev ? (int)$prev->d3_total : 0,
            'd4' => $prev ? (int)$prev->d4_total : 0,
        ];

        // Új totalok – ha FW küld, azt használjuk
        $newTotal = [
            'd1' => ($tFromFw['d1'] !== null) ? $tFromFw['d1'] : ($prevTotal['d1'] + max(0,$d['d1'])),
            'd2' => ($tFromFw['d2'] !== null) ? $tFromFw['d2'] : ($prevTotal['d2'] + max(0,$d['d2'])),
            'd3' => ($tFromFw['d3'] !== null) ? $tFromFw['d3'] : ($prevTotal['d3'] + max(0,$d['d3'])),
            'd4' => ($tFromFw['d4'] !== null) ? $tFromFw['d4'] : ($prevTotal['d4'] + max(0,$d['d4'])),
        ];

        // Bucket-delta idempotensen
        $bucketDelta = [
            'd1' => max(0, $newTotal['d1'] - $prevTotal['d1']),
            'd2' => max(0, $newTotal['d2'] - $prevTotal['d2']),
            'd3' => max(0, $newTotal['d3'] - $prevTotal['d3']),
            'd4' => max(0, $newTotal['d4'] - $prevTotal['d4']),
        ];

        // Mentés (upsert) + diagnosztika
        $saved = false;
        try {
            Pulse::updateOrCreate(
                ['device_id'=>$device->id, 'sample_time'=>$bucket],
                [
                    'd1_delta'=>$bucketDelta['d1'], 'd2_delta'=>$bucketDelta['d2'],
                    'd3_delta'=>$bucketDelta['d3'], 'd4_delta'=>$bucketDelta['d4'],
                    'd1_total'=>$newTotal['d1'],   'd2_total'=>$newTotal['d2'],
                    'd3_total'=>$newTotal['d3'],   'd4_total'=>$newTotal['d4'],
                ]
            );
            $saved = true;
        } catch (\Throwable $e) {
            Log::error('[PULSE] save failed', [
                'device_id'=>$device->id,
                'err' => $e->getMessage(),
            ]);
            return response()->json([
                'ok'=>false,
                'err'=>'db-error',
                'msg'=>$e->getMessage(),
            ], 200, ['Connection'=>'close']); // 200-at adunk vissza, hogy az eszköz ne essen szét, de látod a hibát
        }

        // Fejlesztési diagnosztika a válaszban (élesben kivehető)
        return response()->json([
            'ok'     => true,
            'saved'  => $saved,
            'bucket' => $bucket->toIso8601String(),
            'delta'  => $bucketDelta,
            'total'  => $newTotal,
        ], 200, ['Connection'=>'close']);
    }

    private function maybeConfirmReboot(Device $device, bool $rebootDetected): void
    {
        $cmd = Command::where('device_id', $device->id)
            ->whereIn('cmd', ['reboot','reset'])
            ->where('status', 'done')
            ->where('confirmed', false)
            ->latest('updated_at')
            ->first();

        if (!$cmd) return;

        if ($rebootDetected) {
            $cmd->confirmed = true;
            $cmd->save();
            return;
        }

        if ($device->last_seen_at
            && $device->last_seen_at->gt($cmd->updated_at->copy()->addSeconds(2))
            && $cmd->updated_at->gt(now()->subMinutes(10))) {

            $cmd->confirmed = true;
            $cmd->save();
        }
    }
}
