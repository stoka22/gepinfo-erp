<?php
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MachineController;
use App\Http\Controllers\PendingDeviceController;
use App\Http\Controllers\Scheduler\TaskController;
use App\Http\Controllers\Scheduler\TreeController;
//use App\Http\Controllers\TimeEntryCalendarController;
use App\Http\Controllers\Scheduler\ResourceController;

// 1) Régi /login -> Filament USER login
Route::get('/login', fn () => redirect()->route('filament.user.auth.login'))->name('login');

// Főoldal (maradhat ahogy van)
Route::get('/', function () {
    $d   = today();
    $y22 = $d->copy()->subDay()->setTime(22, 0);
    $t06 = $d->copy()->setTime(6, 0);
    $t14 = $d->copy()->setTime(14, 0);
    $t22 = $d->copy()->setTime(22, 0);
    $t00 = $d->copy()->startOfDay();
    $t24 = $d->copy()->addDay()->startOfDay();

    $machines = Cache::remember('dash:v3:' . now()->format('YmdHi'), 50, function () use ($y22, $t06, $t14, $t22, $t00, $t24) {
        $rows = DB::select(<<<SQL
            SELECT
              m.id, m.name,
              SUM(CASE WHEN p.sample_time >= ? AND p.sample_time < ? THEN COALESCE(p.count,0) ELSE 0 END) AS ej,
              SUM(CASE WHEN p.sample_time >= ? AND p.sample_time < ? THEN COALESCE(p.count,0) ELSE 0 END) AS de,
              SUM(CASE WHEN p.sample_time >= ? AND p.sample_time < ? THEN COALESCE(p.count,0) ELSE 0 END) AS du,
              SUM(CASE WHEN p.sample_time >= ? AND p.sample_time < ? THEN COALESCE(p.count,0) ELSE 0 END) AS ossz,
              COALESCE(MAX(p.created_at), '1970-01-01') AS last_at
            FROM machines m
            LEFT JOIN devices d ON d.machine_id = m.id
            LEFT JOIN pulses  p ON p.device_id  = d.id
            WHERE m.active = 1
            GROUP BY m.id, m.name
            ORDER BY m.name
        SQL, [$y22,$t06,$t06,$t14,$t14,$t22,$t00,$t24]);

        return collect($rows)->map(function ($r) {
            $diff   = $r->last_at ? \Illuminate\Support\Carbon::parse($r->last_at)->diffInMinutes(now()) : PHP_INT_MAX;
            $status = $diff < 5 ? 'green' : ($diff <= 15 ? 'orange' : 'black');
            return (object)[
                'id'       => $r->id,
                'name'     => $r->name,
                'shift_ej' => (int)$r->ej,
                'shift_de' => (int)$r->de,
                'shift_du' => (int)$r->du,
                'osszesen' => (int)$r->ossz,
                'status'   => $status,
            ];
        });
    });

    return response()
        ->view('welcome', ['machines' => $machines])
        ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
        ->header('Pragma', 'no-cache');
});

// Authos nézetek
Route::view('dashboard', 'livewire.dashboard')->middleware(['auth','verified'])->name('dashboard');
Route::view('profile', 'profile')->middleware(['auth'])->name('profile');

// Eszközök/machines
Route::middleware(['auth'])->group(function () {
    Route::view('/devices', 'livewire.devices.index')->name('devices.index');
    Route::post('/devices/approve/{pending}', [PendingDeviceController::class, 'approve'])->name('devices.approve');
    Route::resource('machines', MachineController::class);
    Route::get('/time-entries/calendar-feed', \App\Http\Controllers\TimeEntriesCalendarFeedController::class)
        ->name('time-entries.calendar.events');
    //Route::get('/time-entries/calendar-markers', \App\Http\Controllers\CalendarMarkersController::class)->name('time-entries.calendar.markers');
    Route::get('/time-entries/calendar-markers', \App\Http\Controllers\TimeEntryCalendarMarkersController::class)
        ->name('time-entries.calendar.markers');
});

// ⬇⬇⬇ SCHEDULER – EZ A LÉNYEG ⬇⬇⬇
Route::middleware(['web','auth'])->group(function () {
    // a React oldal (ha külön nézetet használsz Filamenten kívül)
    Route::view('/scheduler', 'scheduler')->name('scheduler.view');

    // a frontend fetch-ek erre hívnak: /api/scheduler/...
    Route::prefix('api/scheduler')->group(function () {
        Route::get('/ping', fn () => response()->json(['ok' => true]));
        Route::get('/resources', [ResourceController::class, 'index']);
        Route::get('/tree', [TreeController::class, 'index']);
        Route::get('/tasks', [TaskController::class, 'index']);
        // (írható végpontok később)
    });

    
   
});
