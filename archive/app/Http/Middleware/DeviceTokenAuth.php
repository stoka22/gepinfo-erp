<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Device;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DeviceTokenAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        if (!$token) {
            return response()->json(['error'=>'missing token'], 401, ['Connection'=>'close']);
        }

        $device = Device::where('device_token', $token)->first();  // <-- ez a helyes oszlopnév
        if (!$device) {
            return response()->json(['error'=>'unauthorized'], 401, ['Connection'=>'close']);
        }

        $request->attributes->set('device', $device);
        return $next($request);
    }
}
