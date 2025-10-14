// --- Add to routes/api.php ---
use App\Http\Controllers\Scheduler\TaskController;

Route::prefix('scheduler')->middleware(['auth:sanctum'])->group(function () {
    Route::get('tasks', [TaskController::class, 'index']);           // existing
    Route::post('splits', [TaskController::class, 'storeSplit']);    // NEW: create/update split (draft)
    Route::post('splits/commit', [TaskController::class, 'commit']); // NEW: commit all visible splits to tasks
    Route::get('occupancy', [TaskController::class, 'occupancy']);   // NEW: time ranges already taken on a machine
});
