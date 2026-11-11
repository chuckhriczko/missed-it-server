<?php
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PuzzleController;
use Illuminate\Support\Facades\Route;
Route::get('/', [PuzzleController::class, 'index'])->name('puzzles.index');
Route::view('/how-to-play', 'guide')->name('guide');
Route::middleware('guest')->group(function () {
    Route::view('/register', 'auth.form', ['register' => true])->name('register');
    Route::view('/login', 'auth.form', ['register' => false])->name('login');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
});
Route::middleware('auth')->group(function () {
    Route::get('/account/tokens', [\App\Http\Controllers\ApiTokenController::class, 'index'])->name('tokens.index');
    Route::post('/account/tokens', [\App\Http\Controllers\ApiTokenController::class, 'store'])->middleware('throttle:token-create')->name('tokens.store');
    Route::delete('/account/tokens/{token}', [\App\Http\Controllers\ApiTokenController::class, 'destroy'])->name('tokens.destroy');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/puzzles/share', [PuzzleController::class, 'create'])->name('puzzles.create');
    Route::post('/puzzles', [PuzzleController::class, 'store'])->middleware('throttle:puzzle-uploads')->name('puzzles.store');
    Route::delete('/puzzles/{puzzle}', [PuzzleController::class, 'destroy'])->name('puzzles.destroy');
});
Route::get('/puzzles/{puzzle}', [PuzzleController::class, 'show'])->name('puzzles.show');
Route::get('/puzzles/{puzzle}/download', [PuzzleController::class, 'download'])->middleware('throttle:60,1')->name('puzzles.download');
