<?php

use Illuminate\Support\Facades\Route;

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
