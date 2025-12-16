<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

// API App Routes (SPA)
Route::get('/', function () {
    return view('api-app');
})->name('api-app');

Route::get('/login', function () {
    return view('api-app');
})->name('api-login');

Route::get('/register', function () {
    return view('api-app');
})->name('api-register');

Route::get('/orders/new', function () {
    return view('api-app');
})->name('api-new-order');

// Legacy Inertia routes (kept for compatibility)
Route::get('/inertia', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__.'/settings.php';
