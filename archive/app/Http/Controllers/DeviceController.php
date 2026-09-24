<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;          // <-- KELL a Str-hez
use App\Models\Device;               // <-- KELL a Device modellhez

class DeviceController extends Controller
{
    public function store(Request $r)
    {
        $data = $r->validate([
            'name'        => 'required|string|max:255',
            'mac_address' => 'required|string|max:255|unique:devices,mac_address',
            'location'    => 'nullable|string|max:255',
            'user_id'     => 'required|exists:users,id',
            'machine_id' => 'nullable|exists:machines,id',
        ]);

        $data['device_token'] = Str::random(48);

        Device::create($data);

        return redirect()->route('devices.index')->with('ok', 'Létrehozva');
    }

    public function update(Request $r, Device $device)
    {
        $data = $r->validate([
            'name'        => 'required|string|max:255',
            // update-nél a saját ID-t ki kell zárni az unique ellenőrzésből
            'mac_address' => 'required|string|max:255|unique:devices,mac_address,'.$device->id,
            'location'    => 'nullable|string|max:255',
            'user_id'     => 'required|exists:users,id',
            'machine_id' => 'nullable|exists:machines,id',
        ]);

        $device->update($data);

        return redirect()->route('devices.index')->with('ok', 'Mentve');
    }
}
