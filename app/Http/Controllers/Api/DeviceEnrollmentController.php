<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\PendingDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Önregisztrációs (self-enrollment) végpont, az Energy projekt
 * EnergyEnrollmentController-jének literál mintája -- EGY különbséggel: itt
 * megmarad a gepinfo mai admin-jóváhagyású folyamata (nincs TOFU). Csak egy
 * már jóváhagyott (Device sor létező mac_address-szel) eszköz kaphat
 * API-kulcsot; egy még nem jóváhagyott (ismeretlen) MAC-ot várólistára tesz
 * (PendingDevice), pontosan úgy, ahogy a törölt DeviceHelloController tette.
 */
class DeviceEnrollmentController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $configuredUser = (string) config('services.gepinfo_device.enrollment_user');
        $configuredPassword = (string) config('services.gepinfo_device.enrollment_password');
        $requestUser = (string) $request->getUser();
        $requestPassword = (string) $request->getPassword();

        if ($configuredUser === '' || $configuredPassword === ''
            || ! hash_equals($configuredUser, $requestUser)
            || ! hash_equals($configuredPassword, $requestPassword)) {
            return response()->json(['message' => 'Unauthorized enrollment.'], 401);
        }

        $validated = $request->validate([
            'device_id' => ['required', 'string', 'regex:/^(?:ESP32|ESP8266)_[0-9A-F]+$/', 'max:32'],
        ]);

        $deviceId = $validated['device_id'];
        $mac = Device::normalizeDeviceId($deviceId);
        $platform = Device::platformFromDeviceId($deviceId);

        if (! $mac || ! $platform) {
            return response()->json(['message' => 'Malformed device_id.'], 422);
        }

        $device = Device::where('mac_address', $mac)->first();

        if (! $device) {
            // Nincs jóváhagyva -- várólistára tesszük, ugyanúgy mint eddig a
            // hello endpoint tette. Az admin a Várakozó eszközök felületen
            // hagyja jóvá; utána a firmware következő /enroll próbálkozása
            // már sikeres lesz.
            PendingDevice::updateOrCreate(
                ['mac_address' => $mac],
                [
                    'proposed_name' => $deviceId,
                    'fw_version' => null,
                    'ip' => $request->ip(),
                    'last_seen_at' => now(),
                ]
            );

            return response()->json(['ok' => false, 'status' => 'pending'], 202);
        }

        if ($device->api_key_hash) {
            // Identitás-eltérítés elleni védelem: a device_id nyilvánosan
            // kitalálható (MAC-alapú), az enrollment jelszó pedig egy
            // megosztott, minden eszközbe befordított titok -- ha egy már
            // kulcsos eszköz újra-enrollmentjét engednénk, bárki, aki ismeri
            // ezt a jelszót, csendben átvehetné egy már üzemelő eszköz
            // identitását. Kulcs-rotáció csak az admin felületen keresztül
            // (Device szerkesztése -> api_key_hash törlése) engedélyezett.
            return response()->json(['message' => 'Device already enrolled.'], 409);
        }

        // A firmware enrollDevice() elutasítja a választ, ha akár api_key,
        // akár ota_pass üres -- mindkettőt ki kell adni. Az ota_pass a helyi
        // (LAN, ArduinoOTA/espota) frissítés jelszava, nem a HTTP push/OTA
        // letöltéshez kell; titkosítva tároljuk a meta-ban, csak ennél az
        // egy válasznál adjuk ki nyersen.
        $apiKey = Str::random(48);
        $otaPassword = Str::random(32);

        $meta = $device->meta ?? [];
        $meta['enrollment'] = [
            'enrolled_at' => now()->toIso8601String(),
            'ota_password' => Crypt::encryptString($otaPassword),
        ];

        $device->forceFill([
            'api_key_hash' => Hash::make($apiKey),
            'platform' => $platform,
            'meta' => $meta,
        ])->save();

        return response()->json([
            'ok' => true,
            'device_id' => $deviceId,
            'api_key' => $apiKey,
            'ota_pass' => $otaPassword,
        ]);
    }
}
