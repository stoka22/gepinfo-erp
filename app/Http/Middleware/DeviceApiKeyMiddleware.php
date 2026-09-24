<?php

namespace App\Http\Middleware;

use App\Models\Device;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Eszköz-hitelesítés az /api/device/push és /api/device/firmware/{version}/download
 * végpontokhoz: device_id (body vagy query) + X-API-KEY fejléc, Device::apiKeyMatches()
 * (bcrypt) ellen ellenőrizve. Nincs TOFU auto-create -- a gepinfo eszköz-jóváhagyás
 * admin-approval alapú marad (lásd DeviceEnrollmentController), ezért ismeretlen
 * device_id-re mindig 401 jár, sosem hoz létre új Device sort.
 */
class DeviceApiKeyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $deviceUid = (string) $request->input('device_id');
        $apiKey = (string) $request->header('X-API-KEY');

        if ($deviceUid === '' || $apiKey === '') {
            return response()->json(['message' => 'Missing device_id or API key.'], 401, ['Connection' => 'close']);
        }

        $mac = Device::normalizeDeviceId($deviceUid);
        $device = $mac ? Device::where('mac_address', $mac)->first() : null;

        if (! $device || ! $device->apiKeyMatches($apiKey)) {
            return response()->json(['message' => 'Unauthorized device or API key.'], 401, ['Connection' => 'close']);
        }

        $request->attributes->set('device', $device);

        return $next($request);
    }
}
