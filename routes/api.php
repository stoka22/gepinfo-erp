<?php // routes/api.php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\DeviceEnrollmentController;
use App\Http\Controllers\Api\DevicePushController;
use App\Http\Controllers\Api\DeviceFirmwareDownloadController;

use App\Http\Controllers\Scheduler\ResourceController;
use App\Http\Controllers\Scheduler\TreeController;
use App\Http\Controllers\Scheduler\TaskController;

use App\Http\Controllers\Api\TerminalWebhookController;
use App\Http\Controllers\Scheduler\ItemApiController;

Route::post('/terminal/event', [TerminalWebhookController::class, 'store'])
    ->middleware('throttle:120,1');

Route::get('/items', ItemApiController::class);

// -----------------------------
// DEVICE endpoints
// -----------------------------
// Self-enrollment (megosztott Basic Auth, admin-jóváhagyás után ad ki
// eszközönkénti API-kulcsot -- lásd DeviceEnrollmentController) és a fő
// push-ciklus (device_id + X-API-KEY, lásd DeviceApiKeyMiddleware).
Route::prefix('device')->group(function () {
    Route::post('/enroll', [DeviceEnrollmentController::class, 'store'])
        ->middleware('throttle:30,1');

    Route::middleware('device.api.key')->group(function () {
        Route::post('/push', [DevicePushController::class, 'store']);

        Route::get('/firmware/{version}/download', DeviceFirmwareDownloadController::class)
            ->name('device.firmware.download');
    });
});

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
