<?php
use App\Http\Controllers\Api\PlayerController;
use App\Http\Controllers\Api\PuzzleController;
use App\Http\Controllers\Api\TokenController;
use App\Http\Controllers\PuzzleController as WebPuzzleController;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;

Route::prefix('v1')->name('api.')->group(function () {
    Route::post('/tokens', [TokenController::class, 'store'])->middleware('throttle:api-login')->name('tokens.store');
    Route::post('/players', [PlayerController::class, 'store'])->middleware('throttle:player-create')->name('players.store');
    Route::middleware('throttle:api-public')->group(function () {
        Route::get('/puzzles', [PuzzleController::class, 'index'])->name('puzzles.index');
        Route::get('/puzzles/{puzzle}', [PuzzleController::class, 'show'])->name('puzzles.show');
        Route::get('/puzzles/{puzzle}/download', [WebPuzzleController::class, 'download'])->name('puzzles.download');
    });
    Route::middleware(['auth:sanctum', 'throttle:api-auth'])->group(function () {
        Route::get('/me', [TokenController::class, 'me'])->name('me');
        Route::patch('/me', [PlayerController::class, 'rename'])->middleware(CheckAbilities::class.':puzzles:write')->name('me.update');
        Route::get('/me/puzzles', [PuzzleController::class, 'index'])->name('me.puzzles');
        Route::delete('/tokens/current', [TokenController::class, 'destroy'])->name('tokens.destroy');
        Route::post('/puzzles', [PuzzleController::class, 'store'])->middleware([CheckAbilities::class.':puzzles:write', 'throttle:puzzle-uploads'])->name('puzzles.store');
        Route::delete('/puzzles/{puzzle}', [PuzzleController::class, 'destroy'])->middleware(CheckAbilities::class.':puzzles:write')->name('puzzles.destroy');
    });
});
