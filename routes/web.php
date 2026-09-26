<?php

use App\Http\Controllers\Admin\ArticleController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\WorkflowRunController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/articles', [ArticleController::class, 'index'])->name('articles.index');
    Route::get('/articles/{article}', [ArticleController::class, 'show'])->name('articles.show');
    Route::get('/workflows/{workflow}', [WorkflowRunController::class, 'show'])->name('workflows.show');
    Route::post('/workflows/{workflow}/retry', [WorkflowRunController::class, 'retry'])->name('workflows.retry');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::get('/health', function () {
    try {
        DB::select('SELECT 1');
        $database = 'ok';
        $status = 200;
    } catch (Throwable) {
        $database = 'unavailable';
        $status = 503;
    }

    return response()->json([
        'status' => $status === 200 ? 'ok' : 'degraded',
        'checks' => [
            'app' => 'ok',
            'database' => $database,
        ],
    ], $status);
})->name('health');

require __DIR__.'/auth.php';
