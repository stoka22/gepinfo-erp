<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Firmware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Hitelesített, eszköz-oldali firmware-letöltés (App\Http\Middleware\
 * DeviceApiKeyMiddleware véd), az Energy projekt FirmwareDownloadController-
 * jének mintája. Az x-MD5 fejléc a feltöltéskor számolt, tárolt hash -- ezt
 * olvassa az ESP32 HTTPUpdate library, és megszakítja a flash-elést, ha a
 * letöltött bájtok nem egyeznek. Soha nem szabad más platform binárisát
 * kiszolgálni, mint amin a kérő eszköz fut.
 */
class DeviceFirmwareDownloadController extends Controller
{
    public function __invoke(Request $request, string $version): StreamedResponse
    {
        /** @var Device $device */
        $device = $request->attributes->get('device');

        $firmware = Firmware::where('version', $version)
            ->where('platform', $device->platform)
            ->firstOrFail();

        return Storage::disk('local')->download($firmware->file_path, $version.'.bin', [
            'Content-Type' => 'application/octet-stream',
            'x-MD5' => $firmware->md5,
        ]);
    }
}
