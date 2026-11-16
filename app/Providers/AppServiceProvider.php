<?php

namespace App\Providers;

use App\Listeners\LinkCustomerAfterVerification;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\ContactMessage;
use App\Models\Customer;
use App\Models\SiteSetting;
use App\Models\User;
use App\Models\Vehicle;
use App\Policies\AuditLogPolicy;
use App\Policies\BookingPolicy;
use App\Policies\ContactMessagePolicy;
use App\Policies\CustomerAccountPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\SiteSettingPolicy;
use App\Policies\VehiclePolicy;
use App\Services\SiteSettings;
use Illuminate\Auth\Events\Verified;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Vehicle::class, VehiclePolicy::class);
        Gate::policy(Booking::class, BookingPolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(User::class, CustomerAccountPolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
        Gate::policy(ContactMessage::class, ContactMessagePolicy::class);
        Gate::policy(SiteSetting::class, SiteSettingPolicy::class);

        Gate::before(function ($user, $ability) {
            if ($ability === 'viewCustomerAccount') {
                return null;
            }

            if ($user && $user->isAdmin()) {
                return true;
            }

            return null;
        });

        RateLimiter::for('login', function (Request $request) {
            return [
                Limit::perMinute(5)->by($request->ip().':'.($request->input('email') ?? 'guest')),
                Limit::perMinute(20)->by($request->ip()),
            ];
        });

        RateLimiter::for('register', function (Request $request) {
            return [
                Limit::perMinute(5)->by($request->ip()),
                Limit::perHour(20)->by($request->ip()),
            ];
        });

        RateLimiter::for('password-reset', function (Request $request) {
            return [
                Limit::perMinute(5)->by($request->ip().':'.($request->input('email') ?? 'guest')),
                Limit::perMinute(20)->by($request->ip()),
            ];
        });

        RateLimiter::for('customer-booking-cancel', function (Request $request) {
            return Limit::perMinute(5)->by($request->user()?->id.':'.$request->ip());
        });

        RateLimiter::for('booking-submission', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('availability', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });

        RateLimiter::for('contact-message', function (Request $request) {
            $email = strtolower(trim((string) $request->input('email')));

            return [
                Limit::perHour(5)->by($request->ip() ?: 'unknown-ip'),
                Limit::perHour(3)->by('contact-message:'.$email),
            ];
        });

        if (Schema::hasTable('site_settings')) {
            app(SiteSettings::class)->applyToConfig();
        }

        Event::listen(Verified::class, LinkCustomerAfterVerification::class);
    }
}
