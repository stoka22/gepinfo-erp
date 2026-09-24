<?php

namespace App\Http\Controllers;

use App\Models\Command;
use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DeviceApiController extends Controller
{
    // GET /api/device/cmd?after=ID&mac=xx&token=yyy
    public function pull(Request $r)
    {
        // 1) azonosítás (válassz: mac+token vagy csak token)
        $mac   = $r->query('mac');          // pl. "AA:BB:CC:DD:EE:FF"
        $token = $r->query('token');

        $q = Device::query();
        if ($mac)   $q->where('mac_address', $mac);
        if ($token) $q->where('device_token', $token);

        $device = $q->first();
        if (!$device) {
            return response()->json(['status' => 'unauthorized'], 401);
        }

        // 2) „after” param: csak az annál nagyobb ID-jú parancsok
        $after = (int) $r->query('after', 0);

        // 3) pending parancsok
        $cmds = Command::query()
            ->where('device_id', $device->id)
            ->where('id', '>', $after)
            ->where('status', 'pending')
            ->orderBy('id')
            ->limit(10)
            ->get(['id','cmd','args']);

        // ha nincs új parancs: adj vissza üres listát (NEM üres body!)
        if ($cmds->isEmpty()) {
            return response()->json([
                'device_id' => $device->id,
                'commands'  => [],
                'last_id'   => $after,
            ]);
        }

        // 4) (opcionális) URL-ek kiegészítése absolute-ra az OTA-hoz
        $enriched = $cmds->map(function ($c) {
            if ($c->cmd === 'ota' && isset($c->args['url'])) {
                // ha relatív, csináljunk absolute URL-t
                if (! str_starts_with($c->args['url'], 'http')) {
                    $c->args['url'] = Storage::url($c->args['url']);
                }
            }
            return [
                'id'  => $c->id,
                'cmd' => $c->cmd,
                'args'=> $c->args,
            ];
        });

        $lastId = $cmds->max('id');

        // 5) visszaadás
        return response()->json([
            'device_id' => $device->id,
            'commands'  => $enriched,
            'last_id'   => $lastId,
        ]);
    }

    // POST /api/device/ack  { "id": 123, "status": "done" | "failed", "message": "..." }
    public function ack(Request $r)
    {
        $id = (int) $r->input('id');
        $status = $r->input('status', 'done');
        $msg = $r->input('message');

        $cmd = Command::find($id);
        if (!$cmd) return response()->json(['ok'=>false,'error'=>'not_found'], 404);

        $cmd->status = in_array($status, ['done','failed','pending']) ? $status : 'done';
        if ($msg) {
            $cmd->result = ['message' => $msg];
        }
        $cmd->save();

        return response()->json(['ok'=>true]);
    }
}
