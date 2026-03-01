<?php

use Illuminate\Support\Facades\Route;

// Main game route
Route::get('/', function () {
    return view('game');
});

// Named route for authentication
Route::get('/login', function () {
    return view('game');
})->name('login');

// Admin Panel Access - View is public, JS handles token-based auth check
Route::get('/admin', [\App\Http\Controllers\Api\AdminController::class, 'index'])->name('admin');

// Catch-all for SPA routing (if needed)
Route::get('/{any}', function () {
    return view('game');
})->where('any', '^(?!api|sanctum).*$');
