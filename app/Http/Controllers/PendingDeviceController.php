<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\PendingDevice;
use App\Models\Device;

class PendingDeviceController extends Controller
{
    public function approve(Request $r, PendingDevice $pending)
    {
        $this->authorize('approve', Device::class); // opcionális: policy admin-only

        // Kinek adjuk? (admin kiválaszthatná UI-ból; itt: az aktuális user kapja)
        $userId = auth()->id();

        $device = Device::create([
            'user_id'     => $userId,
            'name'        => $pending->proposed_name ?: 'Device '.$pending->mac_address,
            'mac_address' => $pending->mac_address,
            'location'    => null,
            'device_token'=> Str::random(48),
        ]);

        $pending->delete();

        return back()->with('ok', 'Eszköz jóváhagyva. Token: '.$device->device_token);
    }
}
