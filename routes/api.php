<?php //routes/api.php

use App\Models\Command;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\PlanningController;
use App\Http\Controllers\SchedulerController;
use App\Http\Controllers\TaskDependencyController;
use App\Http\Controllers\Api\DeviceHelloController;

use App\Http\Controllers\Api\DevicePulseController;
use App\Http\Controllers\Api\DeviceAuthHelloController;

use App\Http\Controllers\Scheduler\ResourceController;
use App\Http\Controllers\Scheduler\TreeController;
use App\Http\Controllers\Scheduler\TaskController;

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

            // → alakítsuk át a sémát az ESP elvárására: id, type, params
            $out = $cmds->map(function ($c) {
                $type   = $c->cmd;
                $params = $c->args ?? [];

                // OTA URL legyen abszolút
                if ($type === 'ota' && !empty($params['url']) && !str_starts_with($params['url'], 'http')) {
                    $params['url'] = \Illuminate\Support\Facades\Storage::url($params['url']);
                }

                return [
                    'id'     => (string)$c->id,    // az ESP String-ként is tudja olvasni
                    'type'   => $type,             // ← EZ a kulcsnév kell a firmware-nek
                    'params' => $params,           // ← EZ a kulcsnév kell a firmware-nek
                ];
            });

            // VAGY: adj vissza közvetlenül TÖMBÖT (ez a legegyszerűbb/legtisztább)
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
            if (!$cmd) return response()->json(['ok' => false, 'error' => 'not_found'], 404);

            $status = in_array($data['status'] ?? 'done', ['done', 'failed', 'cancelled'], true) ? $data['status'] : 'done';
            $cmd->status = $status;
            if (!empty($data['detail'])) $cmd->result = ['message' => $data['detail']];
            $cmd->save();

            return response()->json(['ok' => true]);
        });
    });
});

Route::middleware('auth:sanctum')->group(function () {
    // Scheduler – READ
    Route::get('/scheduler/resources', [ResourceController::class, 'index']);
    Route::get('/scheduler/tree',      [TreeController::class, 'index']);
    Route::get('/scheduler/tasks',     [TaskController::class, 'index']);
    Route::get('/scheduler/shift-window', [\App\Http\Controllers\Scheduler\ShiftController::class, 'window']);


    // Scheduler – WRITE (plural: tasks)
    Route::post  ('/scheduler/tasks',               [TaskController::class, 'store']);
    Route::patch ('/scheduler/tasks/{task}',        [TaskController::class, 'update']);
    Route::post  ('/scheduler/tasks/{task}/move',   [TaskController::class, 'move']);   // gép/idő változás
    Route::post  ('/scheduler/tasks/{task}/resize', [TaskController::class, 'resize']); // időtartam
    Route::delete('/scheduler/tasks/{task}',        [TaskController::class, 'destroy']);
    
});


