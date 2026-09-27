<?php

use App\Http\Controllers\Admin\ArticleController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ReviewController;
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
    Route::get('/articles/{article}/review', [ReviewController::class, 'show'])->name('articles.review');
    Route::get('/generated-assets/{asset}/preview', [ReviewController::class, 'preview'])->name('generated-assets.preview');
    Route::patch('/generated-posts/{post}/draft', [ReviewController::class, 'edit'])->name('review.edit');
    Route::post('/generated-posts/{post}/summary/regenerate', [ReviewController::class, 'regenerateSummary'])->name('review.summary.regenerate');
    Route::post('/generated-posts/{post}/rewrite/regenerate', [ReviewController::class, 'regenerateRewrite'])->name('review.rewrite.regenerate');
    Route::post('/generated-posts/{post}/fact-check', [ReviewController::class, 'rerunFactCheck'])->name('review.fact-check');
    Route::post('/generated-posts/{post}/image/regenerate', [ReviewController::class, 'regenerateImage'])->name('review.image.regenerate');
    Route::post('/articles/{article}/review/manual-image', [ReviewController::class, 'importManualImage'])->name('review.image.import-manual');
    Route::post('/generated-posts/{post}/approve', [ReviewController::class, 'approve'])->name('review.approve');
    Route::post('/generated-posts/{post}/reject', [ReviewController::class, 'reject'])->name('review.reject');
    Route::post('/generated-posts/{post}/request-changes', [ReviewController::class, 'requestChanges'])->name('review.request-changes');
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
