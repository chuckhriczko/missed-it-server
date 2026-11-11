<?php
use App\Http\Controllers\Api\GardenController;
use Illuminate\Support\Facades\Route;
// Read-only endpoints for the game's Puzzle Garden browser. Sharing a puzzle stays on the website, behind sign-in.
Route::prefix('v1')->middleware('throttle:60,1')->group(function () {
    Route::get('/puzzles', [GardenController::class, 'index'])->name('api.puzzles.index');
    Route::get('/puzzles/{puzzle}', [GardenController::class, 'show'])->name('api.puzzles.show');
});
