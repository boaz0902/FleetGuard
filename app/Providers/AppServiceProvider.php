<?php

namespace App\Providers;

use App\Policies\AuditLogPolicy;
use App\Policies\BookingPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\VehiclePolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        Gate::policy(\App\Models\Vehicle::class, VehiclePolicy::class);
        Gate::policy(\App\Models\Booking::class, BookingPolicy::class);
        Gate::policy(\App\Models\Customer::class, CustomerPolicy::class);
        Gate::policy(\App\Models\AuditLog::class, AuditLogPolicy::class);

        Gate::before(function ($user, $ability) {
            if ($user && $user->isAdmin()) {
                return true;
            }

            return null;
        });

        RateLimiter::for('login', function (Request $request) {
            return [
                Limit::perMinute(5)->by($request->ip() . ':' . ($request->input('email') ?? 'guest')),
                Limit::perMinute(20)->by($request->ip()),
            ];
        });

        RateLimiter::for('booking-submission', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('availability', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });
    }
}
