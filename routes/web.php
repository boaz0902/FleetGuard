<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BookingCalendarController;
use App\Http\Controllers\Admin\BookingManagementController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\VehicleAvailabilityController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
})->name('home');

Route::post('/bookings', [BookingController::class, 'store'])
    ->middleware('throttle:booking-submission')
    ->name('bookings.store');

Route::get('/vehicle-availability', VehicleAvailabilityController::class)
    ->middleware('throttle:availability')
    ->name('vehicles.availability');

Route::get('/login', [AuthController::class, 'showLoginForm'])
    ->middleware('guest')
    ->name('login');

Route::middleware('auth')->group(function () {
    Route::get('/bookings/{booking}', [BookingController::class, 'show'])
        ->name('bookings.show');
});

Route::prefix('admin')->name('admin.')->middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:login')
        ->name('login.submit');
});

Route::middleware(['auth', 'can:review,App\\Models\\Booking'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/bookings', [BookingManagementController::class, 'index'])->name('bookings.index');
    Route::get('/bookings/{booking}', [BookingManagementController::class, 'show'])->name('bookings.show');
    Route::patch('/bookings/{booking}/status', [BookingManagementController::class, 'updateStatus'])->name('bookings.status');
    Route::get('/calendar', [BookingCalendarController::class, 'index'])->name('calendar.index');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});

Route::middleware(['auth', 'can:manage,App\\Models\\Vehicle'])->prefix('admin')->name('admin.')->group(function () {
    Route::post('/vehicle-unavailabilities', [BookingCalendarController::class, 'storeUnavailability'])
        ->name('vehicle-unavailabilities.store');
    Route::delete('/vehicle-unavailabilities/{unavailability}', [BookingCalendarController::class, 'destroyUnavailability'])
        ->name('vehicle-unavailabilities.destroy');
});
