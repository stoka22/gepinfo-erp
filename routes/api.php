<?php // routes/api.php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

use App\Http\Controllers\Api\DeviceHelloController;
use App\Http\Controllers\Api\DeviceAuthHelloController;
use App\Http\Controllers\Api\DevicePulseController;

use App\Http\Controllers\Scheduler\ResourceController;
use App\Http\Controllers\Scheduler\TreeController;
use App\Http\Controllers\Scheduler\TaskController;

use App\Http\Controllers\Api\TerminalWebhookController;

Route::post('/terminal/event', [TerminalWebhookController::class, 'store']);


// -----------------------------
// DEVICE endpoints
// -----------------------------
Route::prefix('device')->group(function () {
    Route::post('/hello', [DeviceHelloController::class, 'store'])
        ->middleware('throttle:30,1');

    Route::middleware('auth.device')->group(function () {
        Route::post('/hello-auth', [DeviceAuthHelloController::class, 'store']);
        Route::post('/pulse', [DevicePulseController::class, 'store']);

        Route::get('/cmd', function (Request $r) {
            /** @var \App\Models\Device $device */
            $device = $r->attributes->get('device');

            $after = (int) ($r->query('after', $r->query('since', 0)));

            $cmds = \App\Models\Command::query()
                ->where('device_id', $device->id)
                ->where('id', '>', $after)
                ->where('status', 'pending')
                ->orderBy('id')
                ->limit(10)
                ->get(['id', 'cmd', 'args']);

            $out = $cmds->map(function ($c) {
                $type   = $c->cmd;
                $params = $c->args ?? [];

                if ($type === 'ota' && !empty($params['url']) && !str_starts_with($params['url'], 'http')) {
                    $params['url'] = Storage::url($params['url']);
                }

                return [
                    'id'     => (string) $c->id,
                    'type'   => $type,
                    'params' => $params,
                ];
            });

            return response()->json($out->values(), 200, ['Connection' => 'close']);
        });

        Route::post('/cmd/ack', function (Request $r) {
            $device = $r->attributes->get('device');
            $data = $r->validate([
                'id'     => 'required|integer',
                'status' => 'nullable|string', // done|failed|cancelled
                'detail' => 'nullable|string',
            ]);

            $cmd = \App\Models\Command::where('device_id', $device->id)->find($data['id']);
            if (!$cmd) {
                return response()->json(['ok' => false, 'error' => 'not_found'], 404);
            }

            $status = in_array($data['status'] ?? 'done', ['done', 'failed', 'cancelled'], true)
                ? $data['status']
                : 'done';

            $cmd->status = $status;
            if (!empty($data['detail'])) {
                $cmd->result = ['message' => $data['detail']];
            }
            $cmd->save();

            return response()->json(['ok' => true]);
        });
    });
});

// -----------------------------
// SCHEDULER endpoints (ONE group)
// -----------------------------
// Ha globálisan rá van rakva 'auth:sanctum' az API csoportra, itt explicit levesszük:
// -----------------------------
// SCHEDULER endpoints (ONE group)
// -----------------------------
// Ha globálisan van 'auth:sanctum' az API csoportra, itt vegyük le (frontend sessiont használ):
Route::prefix('scheduler')
    ->withoutMiddleware(['auth:sanctum'])
    ->group(function () {

        // Olvasások
        Route::get('resources',   [ResourceController::class, 'index']);
        Route::get('tree',        [TreeController::class, 'index']);
        Route::get('tasks',       [TaskController::class, 'index']);       // with_totals=1 támogatva
        Route::get('occupancy',   [TaskController::class, 'occupancy']);

        // Írások
        Route::post('tasks',              [TaskController::class, 'store'])->middleware('throttle:60,1');
        Route::patch('tasks/{task}',      [TaskController::class, 'update'])->middleware('throttle:60,1');
        Route::delete('tasks/{task}',     [TaskController::class, 'destroy'])->middleware('throttle:60,1');
        Route::post('tasks/{task}/move',  [TaskController::class, 'move'])->middleware('throttle:60,1');
        Route::post('tasks/{task}/resize',[TaskController::class, 'resize'])->middleware('throttle:60,1');

        // Draft split létrehozás / módosítás / törlés
        Route::post('splits',             [TaskController::class, 'storeSplit'])->middleware('throttle:60,1');
        Route::delete('splits/{split}',   [TaskController::class, 'destroySplit'])->middleware('throttle:60,1');

        // Következő szabad idősáv és műszak-ablak (a SPA hívja)
        Route::get('next-slot',           [TaskController::class, 'nextSlot']);
        Route::get('shift-window',        [TaskController::class, 'shiftWindow']);
    });
