<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Command;
use App\Models\Device;
use App\Models\Firmware;
use App\Models\Pulse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Fő eszköz-push végpont (POST /api/device/push), az Energy projekt
 * EnergyIngestController-jének portja -- `data{}` helyett `pulses_total{d1..d4}`
 * payloaddal. A pontos mezőneveket a már megírt firmware/esp32-counter/src/
 * main.cpp postSample()/enrollDevice() implementációjából olvastam ki
 * (nincs "pulses" delta objektum, csak "pulses_total"; a "commands" válasz-
 * tömb minden elemén csak egy "cmd" kulcs van, nincs id/params/ack).
 */
class DevicePushController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        /** @var Device $device */
        $device = $request->attributes->get('device');

        $validated = $request->validate([
            'device_id' => ['required', 'string', 'max:64'],
            'timestamp' => ['required', 'date'],
            'heartbeat' => ['sometimes', 'boolean'],
            'uptime_seconds' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'ota_enabled' => ['sometimes', 'boolean'],
            'firmware_version' => ['sometimes', 'nullable', 'string', 'max:32'],
            'platform' => ['sometimes', 'nullable', 'string', 'in:esp32,esp8266'],
            'reset_reason' => ['sometimes', 'nullable', 'string', 'max:32'],

            'wifi' => ['sometimes', 'array'],
            'wifi.ssid' => ['sometimes', 'nullable', 'string', 'max:64'],
            'wifi.rssi' => ['sometimes', 'nullable', 'integer'],
            'wifi.ip' => ['sometimes', 'nullable', 'string', 'max:45'],

            'wifi_scan' => ['sometimes', 'array', 'max:10'],
            'wifi_scan.*.ssid' => ['required', 'string', 'max:64'],
            'wifi_scan.*.rssi' => ['nullable', 'integer'],

            'pulses_total' => ['required', 'array'],
            'pulses_total.d1' => ['nullable', 'integer', 'min:0'],
            'pulses_total.d2' => ['nullable', 'integer', 'min:0'],
            'pulses_total.d3' => ['nullable', 'integer', 'min:0'],
            'pulses_total.d4' => ['nullable', 'integer', 'min:0'],
        ]);

        // A live telemetria mindig frissül, a backlog/aktuális minta mentése
        // ELŐTT -- így egy sikertelen mintamentés sem hagyja "offline"-nak
        // tűnni az eszközt.
        $meta = $device->meta ?? [];
        $meta['live'] = [
            'ssid' => $validated['wifi']['ssid'] ?? ($meta['live']['ssid'] ?? null),
            'rssi' => $validated['wifi']['rssi'] ?? ($meta['live']['rssi'] ?? null),
            'ip' => $validated['wifi']['ip'] ?? ($meta['live']['ip'] ?? null),
            'uptime_seconds' => $validated['uptime_seconds'] ?? null,
            'ota_enabled' => $validated['ota_enabled'] ?? ($meta['live']['ota_enabled'] ?? null),
            'reset_reason' => $validated['reset_reason'] ?? ($meta['live']['reset_reason'] ?? null),
            'heartbeat_at' => now()->toIso8601String(),
            // A legutóbbi scan-t megtartjuk, ha ez a push nem hozott újat.
            'wifi_scan' => isset($validated['wifi_scan'])
                ? collect($validated['wifi_scan'])->sortByDesc('rssi')->values()->all()
                : ($meta['live']['wifi_scan'] ?? []),
        ];

        // One-shot provisioning: ha van függőben lévő kulcs-rotáció, ide
        // építjük bele a válaszba, MIELŐTT elmentenénk delivered-nek -- így
        // egy elveszett HTTP-válasz sosem okoz duplikált/soha-meg-nem-kapott
        // rotációt (legfeljebb egyszer újra kiküldjük, ha tényleg elveszett).
        $provisioning = null;
        if (($meta['provisioning']['status'] ?? null) === 'pending') {
            $provisioning = collect($meta['provisioning']['values'] ?? [])
                ->mapWithKeys(fn (string $value, string $key) => [$key => Crypt::decryptString($value)])
                ->all();

            if (! empty($provisioning['api_key'])) {
                $device->api_key_hash = Hash::make($provisioning['api_key']);
            }

            $meta['provisioning'] = [
                'status' => 'delivered',
                'requested_at' => $meta['provisioning']['requested_at'] ?? null,
                'delivered_at' => now()->toIso8601String(),
            ];
        }

        $device->forceFill([
            'last_seen_at' => now(),
            'last_ip' => $request->ip(),
            'fw_version' => $validated['firmware_version'] ?? $device->fw_version,
            'platform' => $validated['platform'] ?? $device->platform,
            'ssid' => $validated['wifi']['ssid'] ?? $device->ssid,
            'rssi' => $validated['wifi']['rssi'] ?? $device->rssi,
            'meta' => $meta,
        ])->save();

        // One-shot parancsok: a Filament admin (DeviceResource "Újraindítás"/
        // "Factory reset" gombjai) által létrehozott pending Command sorok --
        // a firmware applyOneShotCommands()-e csak a "cmd" kulcsot olvassa,
        // nincs külön ack, ezért itt is a küldés PILLANATÁBAN jelöljük
        // kézbesítettnek, a válasz megépítése előtt.
        $pendingCommands = Command::where('device_id', $device->id)
            ->where('status', 'pending')
            ->orderBy('id')
            ->limit(10)
            ->get();

        if ($pendingCommands->isNotEmpty()) {
            Command::whereIn('id', $pendingCommands->pluck('id'))->update(['status' => 'delivered']);
        }

        // Élő minta + esetleges offline backlog mentése -- a backlog-ot
        // szándékosan a validate()-en KÍVÜL, lenient módon dolgozzuk fel,
        // hogy egy hibás/eltorzult backlog sose akadályozza az élő minta
        // mentését.
        foreach ($this->sanitizeBacklog($request->input('backlog')) as $entry) {
            $this->storePulseSample($device, $entry['measured_at'], $entry['totals']);
        }
        $this->storePulseSample($device, Carbon::parse($validated['timestamp']), $validated['pulses_total']);

        $response = [
            'ok' => true,
            'reboot' => false,
            'restart' => false,
            'commands' => $pendingCommands->map(fn (Command $c) => ['cmd' => $c->cmd])->values(),
            'wifi_networks' => $this->decryptedWifiNetworks($device),
        ];

        if ($provisioning) {
            $response['provisioning'] = $provisioning;
        }

        $firmware = $this->firmwareOffer($device, $validated['firmware_version'] ?? null);
        if ($firmware) {
            $response['firmware'] = $firmware;
        }

        // A 'Connection: close' fejlécet szándékosan NEM küldjük itt --
        // korábban itt volt (a törölt DevicePulseController mintáját
        // követve), de a firmware-oldali session gyanúja szerint ez
        // hozzájárulhatott egy ESP8266 BearSSL Soft WDT reset crash-hez
        // az első sikeres push()-nál (2026-09-24). Az /enroll válasza sosem
        // küldte ezt a fejlécet, és az sosem omlott össze -- kérésükre
        // eltávolítva innen is, hogy a két végpont válasza ebből a
        // szempontból konzisztens legyen.
        return response()->json($response);
    }

    /**
     * Egy adott percre bucketolt minta mentése: az előző (ennél korábbi)
     * mentett total-okhoz képest számolt, sosem negatív delta -- ugyanaz a
     * logika, mint amit a törölt DevicePulseController is használt, csak a
     * kompat "pulses"/"count"/"sample_id" mezők nélkül, mert a valódi
     * firmware sosem küldte azokat, csak a "pulses_total"-t.
     */
    private function storePulseSample(Device $device, Carbon $measuredAt, array $totals): void
    {
        $bucket = $measuredAt->copy()->second(0)->microsecond(0);

        $prev = Pulse::where('device_id', $device->id)
            ->where('sample_time', '<', $bucket)
            ->orderByDesc('sample_time')
            ->first();

        $prevTotal = [
            'd1' => (int) ($prev->d1_total ?? 0),
            'd2' => (int) ($prev->d2_total ?? 0),
            'd3' => (int) ($prev->d3_total ?? 0),
            'd4' => (int) ($prev->d4_total ?? 0),
        ];

        $newTotal = [
            'd1' => (int) ($totals['d1'] ?? $prevTotal['d1']),
            'd2' => (int) ($totals['d2'] ?? $prevTotal['d2']),
            'd3' => (int) ($totals['d3'] ?? $prevTotal['d3']),
            'd4' => (int) ($totals['d4'] ?? $prevTotal['d4']),
        ];

        try {
            Pulse::updateOrCreate(
                ['device_id' => $device->id, 'sample_time' => $bucket],
                [
                    'd1_delta' => max(0, $newTotal['d1'] - $prevTotal['d1']),
                    'd2_delta' => max(0, $newTotal['d2'] - $prevTotal['d2']),
                    'd3_delta' => max(0, $newTotal['d3'] - $prevTotal['d3']),
                    'd4_delta' => max(0, $newTotal['d4'] - $prevTotal['d4']),
                    'd1_total' => $newTotal['d1'],
                    'd2_total' => $newTotal['d2'],
                    'd3_total' => $newTotal['d3'],
                    'd4_total' => $newTotal['d4'],
                ]
            );
        } catch (\Throwable $e) {
            Log::error('[PUSH] pulse save failed', ['device_id' => $device->id, 'err' => $e->getMessage()]);
        }
    }

    /**
     * A backlog mezőt szándékosan a $request->validate()-en kívül olvassuk
     * (nem a validált tömbből), hogy egy hibás/túlméretezett backlog sose
     * dobjon 422-t az egész kérésre -- csak a hibás bejegyzéseket dobjuk el
     * csendben, az élő minta mentése ettől függetlenül mindig megtörténik.
     */
    private function sanitizeBacklog(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $sanitized = [];
        foreach (array_slice($raw, 0, 5) as $entry) {
            if (! is_array($entry) || empty($entry['timestamp']) || ! is_string($entry['timestamp'])) {
                continue;
            }

            try {
                $measuredAt = Carbon::parse($entry['timestamp']);
            } catch (\Throwable) {
                continue;
            }

            $totalsRaw = $entry['pulses_total'] ?? null;
            if (! is_array($totalsRaw)) {
                continue;
            }

            $totals = [];
            foreach (['d1', 'd2', 'd3', 'd4'] as $key) {
                if (isset($totalsRaw[$key]) && is_numeric($totalsRaw[$key])) {
                    $totals[$key] = (int) $totalsRaw[$key];
                }
            }
            if (empty($totals)) {
                continue;
            }

            $sanitized[] = ['measured_at' => $measuredAt, 'totals' => $totals];
        }

        // Régiről az újra: a backlog a firmware oldalán oldest-first, de a
        // biztonság kedvéért itt is időrend szerint rendezzük, mert a
        // delta-számítás csak így helyes (mindig a KORÁBBI total-hoz képest).
        usort($sanitized, fn ($a, $b) => $a['measured_at']->timestamp <=> $b['measured_at']->timestamp);

        return $sanitized;
    }

    /**
     * A devices.meta.wifi_networks -- admin által, eszközönként megadható,
     * priorizált SSID/jelszó lista (DeviceResource "WiFi hálózatok" repeater).
     * A firmware savePreferredNetworksIfChanged()-je ezt a pontos alakot
     * várja: [{ssid, password}, ...], sima szöveges jelszóval -- a tárolás
     * titkosított (Crypt::encryptString), csak ebben a válaszban dekódolva,
     * pontosan az Energy projekt decryptedWifiNetworks()-mintája szerint.
     * Mindig visszaadjuk (üres tömbként is), mert a firmware minden push-
     * válaszban megnézi, nem csak első alkalommal.
     */
    private function decryptedWifiNetworks(Device $device): array
    {
        return collect($device->meta['wifi_networks'] ?? [])
            ->map(fn (array $network) => [
                'ssid' => $network['ssid'],
                'password' => $this->decryptWifiPassword($network['password'] ?? null),
            ])
            ->values()
            ->all();
    }

    private function decryptWifiPassword(?string $value): ?string
    {
        if (! $value) {
            return $value;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Illuminate\Contracts\Encryption\DecryptException) {
            return $value;
        }
    }

    /**
     * A devices.meta.firmware_target_version -- eszközönkénti, admin által
     * beállított cél (nem flotta-szintű flag, lásd Energy projekt döntése:
     * "nem akarok automatikus tömeges flotta frissítést"). Csak akkor
     * ajánljuk fel, ha van cél, eltér a bejelentett verziótól, ÉS a
     * platform (esp32/esp8266) egyezik -- soha nem szabad más platform
     * binárisát felajánlani.
     */
    private function firmwareOffer(Device $device, ?string $reportedVersion): ?array
    {
        $target = $device->meta['firmware_target_version'] ?? null;
        if (! $target || $target === $reportedVersion) {
            return null;
        }

        $firmware = Firmware::where('version', $target)
            ->where('platform', $device->platform)
            ->first();

        $deviceIdString = $device->deviceIdString();
        if (! $firmware || ! $deviceIdString) {
            return null;
        }

        return [
            'version' => $firmware->version,
            'url' => route('device.firmware.download', [
                'version' => $firmware->version,
                'device_id' => $deviceIdString,
            ]),
        ];
    }
}
