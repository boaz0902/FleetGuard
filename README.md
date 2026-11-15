<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## FleetGuard backend foundation

This project now includes the backend foundation for FleetGuard's rental booking workflow:

- Vehicle, customer, booking, and audit log models with Laravel migrations.
- Secure admin authentication using Laravel's session guard with role checks.
- Booking request validation and conflict prevention for overlapping vehicle reservations.
- Booking status workflow that keeps requests in pending state until an authorized admin approves or rejects the request.
- Backend-calculated rental totals and daily-rate snapshots to protect pricing integrity.

Booking submissions are rate-limited and protected by Laravel CSRF and session security defaults. Authenticated administrators and managers can review the two-week vehicle calendar; only administrators can create or remove vehicle maintenance periods.

The public `GET /vehicle-availability` endpoint returns current database vehicle rates for a requested date/time range. Only active vehicles are listed. Approved and ongoing bookings and overlapping maintenance periods make a vehicle unavailable; pending, rejected, cancelled, and completed bookings do not. Return time is the exclusive end boundary, and business times use Asia/Manila. Booking submission and approval recheck availability inside a transaction while holding the vehicle row lock. Admin and manager booking status changes, and administrator maintenance changes, are audited. Customer booking submissions do not overwrite existing customer contact records.

Role assignment is backend-controlled; customer self-registration never accepts a role, and role management is not included. Customer sign-in is at `/login`; administrator sign-in remains at `/admin/login`. Customer accounts use email verification, password reset, and verified-email-only linking to a matching existing guest customer record. Verified customers can review their booking history and cancel pending requests. Staff are denied booking and calendar access until assigned-booking authorization exists. Seed an admin only by setting `APP_ADMIN_EMAIL` and `APP_ADMIN_PASSWORD` in the environment before running the seeder; the seeder creates no default admin credentials.

For local development, the `log` mailer writes verification and password-reset messages (including their links) to `storage/logs/mail.log`, not the application log. Do not use the log mailer in production; configure a delivery provider through `MAIL_MAILER` and its credentials instead.

## Public website configuration

The public FleetGuard site includes the fleet showcase, booking-request steps, About, FAQ, and Contact sections. The fleet inventory, images, rates, and availability are sample/unverified unless they are configured as live vehicle records; availability and rates are confirmed by FleetGuard. Booking requests are reviewed by the team and do not take payment.

Set public business details in the environment using `FLEETGUARD_PHONE`, `FLEETGUARD_EMAIL`, `FLEETGUARD_ADDRESS`, `FLEETGUARD_BUSINESS_HOURS`, and `FLEETGUARD_MAPS_URL`. Optional social profile URLs use `FLEETGUARD_SOCIAL_FACEBOOK`, `FLEETGUARD_SOCIAL_INSTAGRAM`, and `FLEETGUARD_SOCIAL_TIKTOK`. All values are optional. When unset, the site displays “To be added”; map and social links are rendered only for HTTP or HTTPS URLs. These settings are read from `config/fleetguard.php`.

The public navbar links to section anchors on the home page and to the corresponding home-page anchor from other customer-facing pages. Customer account actions reflect sign-in and email-verification state; administrator and manager sessions are not given a public admin-portal link.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
