<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PendingDevice;
use App\Models\Device;

class PendingDeviceController extends Controller
{
    public function approve(Request $r, PendingDevice $pending)
    {
        $this->authorize('approve', Device::class); // opcionális: policy admin-only

        // Kinek adjuk? (admin kiválaszthatná UI-ból; itt: az aktuális user kapja)
        $userId = auth()->id();

        // Nincs device_token többé -- a jóváhagyás után az eszköz a saját
        // /api/device/enroll hívásával szerzi meg az API-kulcsát
        // (DeviceEnrollmentController), a mac_address alapján azonosítva.
        $device = Device::create([
            'user_id'     => $userId,
            'name'        => $pending->proposed_name ?: 'Device '.$pending->mac_address,
            'mac_address' => $pending->mac_address,
            'location'    => null,
        ]);

        $pending->delete();

        return back()->with('ok', "Eszköz jóváhagyva ({$device->name}). A firmware a következő próbálkozásnál automatikusan megkapja az API-kulcsát.");
    }
}
