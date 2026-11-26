<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BookingCalendarController;
use App\Http\Controllers\Admin\BookingManagementController;
use App\Http\Controllers\Admin\ContactMessagesController as AdminContactMessagesController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FleetController;
use App\Http\Controllers\Admin\LegalPolicyController as AdminLegalPolicyController;
use App\Http\Controllers\Admin\RentalController;
use App\Http\Controllers\Admin\ReportsController;
use App\Http\Controllers\Admin\SiteSettingsController;
use App\Http\Controllers\Admin\TestimonialController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\ContactMessagesController;
use App\Http\Controllers\CustomerAccountController;
use App\Http\Controllers\CustomerAuthController;
use App\Http\Controllers\CustomerBookingsController;
use App\Http\Controllers\CustomerTestimonialController;
use App\Http\Controllers\CustomerPasswordController;
use App\Http\Controllers\LegalPolicyController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\VehicleAvailabilityController;
use App\Models\LegalPolicy;
use App\Models\Testimonial;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Route;

Route::get('/robots.txt', [SeoController::class, 'robots'])->name('seo.robots');
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('seo.sitemap');

Route::get('/', function () {
    $vehicles = Vehicle::query()
        ->whereIn('status', [Vehicle::STATUS_ACTIVE, Vehicle::STATUS_MAINTENANCE])
        ->orderBy('name')
        ->get()
        ->map(fn (Vehicle $vehicle): array => [
            'id' => $vehicle->id,
            'name' => $vehicle->name,
            'category' => strtolower($vehicle->category),
            'category_label' => strtoupper($vehicle->category),
            'passengers' => $vehicle->passenger_capacity.' passengers',
            'transmission' => $vehicle->transmission,
            'fuel' => $vehicle->fuel_type,
            'price' => '₱'.number_format((float) $vehicle->daily_rate, 2),
            'daily_rate' => $vehicle->daily_rate,
            'image' => $vehicle->image_path,
        ]);

    $asOf = now();
    $agreementVersions = LegalPolicy::query()
        ->whereIn('slug', LegalPolicy::bookingAgreementSlugs())
        ->where('is_published', true)
        ->get()
        ->mapWithKeys(function (LegalPolicy $policy) use ($asOf): array {
            $version = $policy->currentPublishedVersionAt($asOf);

            return $version ? [$policy->slug => $version->id] : [];
        })
        ->all();

    return view('home', [
        'vehicles' => $vehicles,
        'categories' => $vehicles->pluck('category')->unique()->sort()->values(),
        'agreementVersions' => $agreementVersions,
        'testimonials' => Testimonial::query()->published()->ordered()->get(),
        'googleReviews' => [
            'business_name' => config('fleetguard.google_business_name'),
            'read_url' => config('fleetguard.google_reviews_url'),
            'write_url' => config('fleetguard.google_write_review_url') ?: (
                config('fleetguard.google_place_id')
                    ? 'https://search.google.com/local/writereview?placeid='.rawurlencode((string) config('fleetguard.google_place_id'))
                    : null
            ),
            'rating' => config('fleetguard.show_google_rating') === '1' ? config('fleetguard.google_rating') : null,
            'review_count' => config('fleetguard.show_google_review_count') === '1' ? config('fleetguard.google_review_count') : null,
        ],
    ]);
})->name('home');

Route::get('/fleet', function () {
    $vehicles = Vehicle::query()
        ->whereIn('status', [Vehicle::STATUS_ACTIVE, Vehicle::STATUS_MAINTENANCE])
        ->orderBy('name')
        ->get();

    return view('fleet.index', [
        'vehicles' => $vehicles,
        'categories' => $vehicles->pluck('category')->filter()->unique()->sort()->values(),
    ]);
})->name('fleet');

Route::get('/fleet/{vehicle}', function (Vehicle $vehicle) {
    abort_unless(in_array($vehicle->status, [Vehicle::STATUS_ACTIVE, Vehicle::STATUS_MAINTENANCE], true), 404);

    $similar = Vehicle::query()
        ->whereIn('status', [Vehicle::STATUS_ACTIVE, Vehicle::STATUS_MAINTENANCE])
        ->where('id', '!=', $vehicle->id)
        ->where('category', $vehicle->category)
        ->orderBy('name')
        ->limit(3)
        ->get();

    if ($similar->count() < 3) {
        $fallback = Vehicle::query()
            ->whereIn('status', [Vehicle::STATUS_ACTIVE, Vehicle::STATUS_MAINTENANCE])
            ->where('id', '!=', $vehicle->id)
            ->whereNotIn('id', $similar->pluck('id'))
            ->orderBy('name')
            ->limit(3 - $similar->count())
            ->get();
        $similar = $similar->concat($fallback);
    }

    return view('fleet.show', [
        'vehicle' => $vehicle,
        'similar' => $similar,
    ]);
})->name('fleet.show');

Route::get('/booking', function () {
    $agreementVersions = LegalPolicy::query()
        ->whereIn('slug', LegalPolicy::bookingAgreementSlugs())
        ->where('is_published', true)
        ->get()
        ->mapWithKeys(function (LegalPolicy $policy): array {
            $version = $policy->currentPublishedVersionAt(now());

            return $version ? [$policy->slug => $version->id] : [];
        })
        ->all();

    return view('booking.index', [
        'agreementVersions' => $agreementVersions,
    ]);
})->name('booking');

Route::get('/policies/{slug}', [LegalPolicyController::class, 'show'])->name('legal.show');

Route::post('/bookings', [BookingController::class, 'store'])
    ->middleware('throttle:booking-submission')
    ->name('bookings.store');

Route::get('/vehicle-availability', VehicleAvailabilityController::class)
    ->middleware('throttle:availability')
    ->name('vehicles.availability');

Route::get('/vehicles/{vehicle}/schedule', [VehicleAvailabilityController::class, 'schedule'])
    ->middleware('throttle:availability')
    ->name('vehicles.schedule');

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

Route::middleware(['auth', 'customer', 'auth.session'])->group(function () {
    Route::get('/account', [CustomerAccountController::class, 'show'])->name('account');
    Route::get('/account/profile', [CustomerAccountController::class, 'editProfile'])->name('account.profile');
    Route::put('/account/profile', [CustomerAccountController::class, 'updateProfile'])
        ->middleware('throttle:30,1')
        ->name('account.profile.update');
    Route::put('/account/profile/password', [CustomerAccountController::class, 'changePassword'])
        ->middleware('throttle:5,1')
        ->name('account.profile.password');
    Route::get('/account/bookings', [CustomerBookingsController::class, 'index'])
        ->name('account.bookings');
    Route::get('/account/bookings/{booking}/testimonial', [CustomerTestimonialController::class, 'create'])
        ->name('account.bookings.testimonials.create');
    Route::post('/account/bookings/{booking}/testimonial', [CustomerTestimonialController::class, 'store'])
        ->middleware('throttle:customer-testimonial-submission')
        ->name('account.bookings.testimonials.store');
    Route::get('/account/legal-policies', [CustomerAccountController::class, 'legalPolicies'])
        ->name('account.legal-policies');
    Route::post('/account/bookings/{booking}/cancel', [CustomerBookingsController::class, 'cancel'])
        ->middleware('throttle:customer-booking-cancel')
        ->name('account.bookings.cancel');
    Route::get('/email/verify', [CustomerAccountController::class, 'verificationNotice'])
        ->name('verification.notice');
    Route::post('/email/verify', [CustomerAccountController::class, 'verify'])
        ->middleware('throttle:6,1')
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
    Route::get('/bookings/{booking}/documents/{document}', [BookingManagementController::class, 'document'])
        ->name('bookings.documents.show');
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
    Route::delete('/fleet/{vehicle}', [FleetController::class, 'destroy'])
        ->middleware('can:delete,vehicle')
        ->name('fleet.destroy');
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
});

Route::middleware(['auth', 'can:viewAny,App\\Models\\ContactMessage'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/messages', [AdminContactMessagesController::class, 'index'])->name('messages.index');
    Route::get('/messages/{contactMessage}', [AdminContactMessagesController::class, 'show'])->name('messages.show');
    Route::patch('/messages/{contactMessage}/status', [AdminContactMessagesController::class, 'updateStatus'])->name('messages.status');
});

Route::middleware(['auth', 'can:viewAny,App\\Models\\SiteSetting'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/settings', [SiteSettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings', [SiteSettingsController::class, 'update'])->name('settings.update');
    Route::post('/settings/preview', [SiteSettingsController::class, 'preview'])
        ->middleware('throttle:30,1')
        ->name('settings.preview');
});

Route::middleware(['auth', 'can:viewAny,App\\Models\\LegalPolicy'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/legal-policies', [AdminLegalPolicyController::class, 'index'])->name('legal-policies.index');
    Route::get('/legal-policies/acceptances', [AdminLegalPolicyController::class, 'acceptances'])->name('legal-policies.acceptances');
    Route::get('/legal-policies/{legalPolicy}/preview', [AdminLegalPolicyController::class, 'preview'])->name('legal-policies.preview');
    Route::get('/legal-policies/{legalPolicy}/edit', [AdminLegalPolicyController::class, 'edit'])->name('legal-policies.edit');
    Route::put('/legal-policies/{legalPolicy}', [AdminLegalPolicyController::class, 'update'])->name('legal-policies.update');
    Route::post('/legal-policies/{legalPolicy}/versions/{version}/publish', [AdminLegalPolicyController::class, 'publish'])->name('legal-policies.publish');
    Route::post('/legal-policies/{legalPolicy}/unpublish', [AdminLegalPolicyController::class, 'unpublish'])->name('legal-policies.unpublish');
});

Route::middleware(['auth', 'can:viewAny,App\\Models\\Testimonial'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/testimonials', [TestimonialController::class, 'index'])->name('testimonials.index');
    Route::get('/testimonials/create', [TestimonialController::class, 'create'])
        ->middleware('can:create,App\\Models\\Testimonial')
        ->name('testimonials.create');
    Route::post('/testimonials', [TestimonialController::class, 'store'])
        ->middleware('can:create,App\\Models\\Testimonial')
        ->name('testimonials.store');
    Route::get('/testimonials/{testimonial}/edit', [TestimonialController::class, 'edit'])
        ->middleware('can:view,testimonial')
        ->name('testimonials.edit');
    Route::put('/testimonials/{testimonial}', [TestimonialController::class, 'update'])
        ->middleware('can:update,testimonial')
        ->name('testimonials.update');
    Route::post('/testimonials/{testimonial}/publish', [TestimonialController::class, 'publish'])
        ->middleware('can:publish,testimonial')
        ->name('testimonials.publish');
    Route::post('/testimonials/{testimonial}/reject', [TestimonialController::class, 'reject'])
        ->middleware('can:publish,testimonial')
        ->name('testimonials.reject');
    Route::post('/testimonials/{testimonial}/unpublish', [TestimonialController::class, 'unpublish'])
        ->middleware('can:publish,testimonial')
        ->name('testimonials.unpublish');
    Route::post('/testimonials/{testimonial}/featured', [TestimonialController::class, 'toggleFeatured'])
        ->middleware('can:feature,testimonial')
        ->name('testimonials.featured');
    Route::post('/testimonials/{testimonial}/order', [TestimonialController::class, 'reorder'])
        ->middleware('can:reorder,testimonial')
        ->name('testimonials.order');
    Route::delete('/testimonials/{testimonial}', [TestimonialController::class, 'destroy'])
        ->middleware('can:delete,testimonial')
        ->name('testimonials.destroy');
});
