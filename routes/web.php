<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BookingCalendarController;
use App\Http\Controllers\Admin\BookingManagementController;
use App\Http\Controllers\Admin\ContactMessagesController as AdminContactMessagesController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FleetController;
use App\Http\Controllers\Admin\RentalController;
use App\Http\Controllers\Admin\ReportsController;
use App\Http\Controllers\Admin\SiteSettingsController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\ContactMessagesController;
use App\Http\Controllers\CustomerAccountController;
use App\Http\Controllers\CustomerAuthController;
use App\Http\Controllers\CustomerBookingsController;
use App\Http\Controllers\CustomerPasswordController;
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

Route::get('/login', [CustomerAuthController::class, 'showLoginForm'])
    ->middleware('guest')
    ->name('login');

Route::post('/login', [CustomerAuthController::class, 'login'])
    ->middleware(['guest', 'throttle:login'])
    ->name('login.submit');

Route::get('/register', [CustomerAuthController::class, 'showRegistrationForm'])
    ->middleware('guest')
    ->name('register');

Route::post('/register', [CustomerAuthController::class, 'register'])
    ->middleware(['guest', 'throttle:register'])
    ->name('register.submit');

Route::get('/forgot-password', [CustomerPasswordController::class, 'showRequestForm'])
    ->middleware('guest')
    ->name('password.request');

Route::post('/forgot-password', [CustomerPasswordController::class, 'sendResetLink'])
    ->middleware(['guest', 'throttle:password-reset'])
    ->name('password.email');

Route::get('/reset-password/{token}', [CustomerPasswordController::class, 'showResetForm'])
    ->middleware('guest')
    ->name('password.reset');

Route::post('/reset-password', [CustomerPasswordController::class, 'reset'])
    ->middleware(['guest', 'throttle:password-reset'])
    ->name('password.update');

Route::middleware(['auth', 'customer'])->group(function () {
    Route::get('/account', [CustomerAccountController::class, 'show'])->name('account');
    Route::get('/account/bookings', [CustomerBookingsController::class, 'index'])
        ->name('account.bookings');
    Route::post('/account/bookings/{booking}/cancel', [CustomerBookingsController::class, 'cancel'])
        ->middleware('throttle:customer-booking-cancel')
        ->name('account.bookings.cancel');
    Route::get('/email/verify', [CustomerAccountController::class, 'verificationNotice'])
        ->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [CustomerAccountController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('/email/verification-notification', [CustomerAccountController::class, 'resendVerification'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
    Route::post('/logout', [CustomerAuthController::class, 'logout'])->name('logout');
});

Route::middleware('auth')->group(function () {
    Route::get('/bookings/{booking}', [BookingController::class, 'show'])
        ->name('bookings.show');
});

Route::post('/contact', [ContactMessagesController::class, 'store'])
    ->middleware('throttle:contact-message')
    ->name('contact.store');

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
    Route::get('/rentals', [RentalController::class, 'index'])->name('rentals.index');
    Route::get('/rentals/{booking}', [RentalController::class, 'show'])->name('rentals.show');
    Route::post('/rentals/{booking}/checkout', [RentalController::class, 'checkOut'])->name('rentals.checkout');
    Route::post('/rentals/{booking}/checkin', [RentalController::class, 'checkIn'])->name('rentals.checkin');
    Route::get('/calendar', [BookingCalendarController::class, 'index'])->name('calendar.index');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});

Route::middleware(['auth', 'can:viewAny,App\\Models\\AuditLog'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    Route::get('/reports', [ReportsController::class, 'index'])->name('reports.index');
    Route::get('/reports/export', [ReportsController::class, 'export'])->name('reports.export');
});

Route::middleware(['auth', 'can:manage,App\\Models\\Vehicle'])->prefix('admin')->name('admin.')->group(function () {
    Route::post('/vehicle-unavailabilities', [BookingCalendarController::class, 'storeUnavailability'])
        ->name('vehicle-unavailabilities.store');
    Route::delete('/vehicle-unavailabilities/{unavailability}', [BookingCalendarController::class, 'destroyUnavailability'])
        ->name('vehicle-unavailabilities.destroy');
    Route::post('/fleet/maintenance', [BookingCalendarController::class, 'storeUnavailability'])
        ->name('fleet.maintenance.store');
    Route::delete('/fleet/maintenance/{unavailability}', [BookingCalendarController::class, 'destroyUnavailability'])
        ->name('fleet.maintenance.destroy');
});

Route::middleware(['auth', 'can:viewAny,App\\Models\\Vehicle'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/fleet', [FleetController::class, 'index'])->name('fleet.index');
    Route::get('/fleet/create', [FleetController::class, 'create'])
        ->middleware('can:create,App\\Models\\Vehicle')
        ->name('fleet.create');
    Route::post('/fleet', [FleetController::class, 'store'])
        ->middleware('can:create,App\\Models\\Vehicle')
        ->name('fleet.store');
    Route::get('/fleet/{vehicle}/edit', [FleetController::class, 'edit'])
        ->middleware('can:update,vehicle')
        ->name('fleet.edit');
    Route::put('/fleet/{vehicle}', [FleetController::class, 'update'])
        ->middleware('can:update,vehicle')
        ->name('fleet.update');
    Route::get('/fleet/{vehicle}', [FleetController::class, 'show'])
        ->middleware('can:view,vehicle')
        ->name('fleet.show');
});

Route::middleware(['auth', 'can:viewAny,App\\Models\\Customer'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
    Route::post('/customers/{customer}/notes', [CustomerController::class, 'storeNote'])
        ->middleware('can:addNote,customer')
        ->name('customers.notes.store');
    Route::post('/customers/{customer}/block', [CustomerController::class, 'block'])
        ->middleware('can:manageStatus,customer')
        ->name('customers.block');
    Route::post('/customers/{customer}/unblock', [CustomerController::class, 'unblock'])
        ->middleware('can:manageStatus,customer')
        ->name('customers.unblock');
    Route::get('/customers/{customer}/edit', [CustomerController::class, 'edit'])
        ->middleware('can:update,customer')
        ->name('customers.edit');
    Route::put('/customers/{customer}', [CustomerController::class, 'update'])
        ->middleware('can:update,customer')
        ->name('customers.update');
});

Route::middleware(['auth', 'can:viewAny,App\\Models\\ContactMessage'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/messages', [AdminContactMessagesController::class, 'index'])->name('messages.index');
    Route::get('/messages/{contactMessage}', [AdminContactMessagesController::class, 'show'])->name('messages.show');
    Route::patch('/messages/{contactMessage}/status', [AdminContactMessagesController::class, 'updateStatus'])->name('messages.status');
});

Route::middleware(['auth', 'can:viewAny,App\\Models\\SiteSetting'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/settings', [SiteSettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings', [SiteSettingsController::class, 'update'])->name('settings.update');
});
