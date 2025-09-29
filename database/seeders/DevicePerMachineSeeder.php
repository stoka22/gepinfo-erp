<?php

namespace Database\Seeders;

use App\Models\Device;
use App\Models\Machine;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DevicePerMachineSeeder extends Seeder
{
    public function run(): void
    {
        // tulaj user (ha van AdminUserSeeder-ed, ez már megvan)
        $ownerId = optional(User::query()->first())->id;

        foreach (Machine::cursor() as $machine) {
            // ha már van eszköz ehhez a géphez, hagyjuk békén
            $existing = Device::where('machine_id', $machine->id)->first();
            if ($existing) {
                continue;
            }

            // eszköz név és MAC
            $name = 'Device - '.$machine->name;
            $mac  = $this->randomMac();

            Device::create([
                'user_id'      => $ownerId,                 // ha a mező nullable, maradhat null is
                'machine_id'   => $machine->id,             // <- hozzárendelés a géphez
                'name'         => $name,
                'mac_address'  => $mac,
                'location'     => $machine->location ?? null,
                'device_token' => Str::random(48),
                // ha vannak extra (NOT NULL) mezőid a devices táblában, itt töltsd ki őket is
            ]);
        }
    }

    private function randomMac(): string
    {
        $octets = [];
        for ($i = 0; $i < 6; $i++) {
            $octets[] = str_pad(dechex(random_int(0, 255)), 2, '0', STR_PAD_LEFT);
        }
        return strtoupper(implode(':', $octets));
    }
}
