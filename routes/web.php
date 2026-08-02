<?php

use Illuminate\Support\Facades\Route;

Route::get('/up', fn () => response('ok', 200));

// Satu-satunya halaman web: SPA sederhana yang mengonsumsi REST API di /api.
Route::view('/', 'booking')->name('home');
