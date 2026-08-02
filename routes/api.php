<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\RouteController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\SeatLockController;
use Illuminate\Support\Facades\Route;


Route::post('/auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:6,1')
    ->name('auth.login');
Route::post('/auth/refresh', [AuthController::class, 'refresh'])
    ->middleware('throttle:10,1')
    ->name('auth.refresh');

Route::get('/routes', [RouteController::class, 'index'])->name('routes.index');
Route::get('/schedules', [ScheduleController::class, 'index'])
    ->middleware('throttle:10,1')
    ->name('schedules.index');
Route::get('/schedules/{schedule}/seats', [ScheduleController::class, 'seats'])
    ->middleware('throttle:10,1')
    ->name('schedules.seats');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

    Route::post('/seat-locks', [SeatLockController::class, 'store'])->name('seat-locks.store');
    Route::delete('/seat-locks/{lockToken}', [SeatLockController::class, 'destroy'])->name('seat-locks.destroy');

    Route::get('/bookings', [BookingController::class, 'index'])->name('bookings.index');
    Route::post('/bookings', [BookingController::class, 'store'])->name('bookings.store');
    Route::get('/bookings/{booking}', [BookingController::class, 'show'])->name('bookings.show');
});
