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

## Private booking documents

Booking identity documents use the dedicated `private` disk by default, rooted at `storage/app/private` and not served directly by Laravel's local-disk serving route. The authorized booking-review endpoint streams the files after checking access. Set `BOOKING_DOCUMENTS_DISK` to the name of another explicitly configured private Laravel disk when deploying to a private object-storage provider; never point it at the `public` disk or enable public bucket access. Existing uploads are not moved automatically when changing disks: copy and verify them at their existing paths before switching. The application does not automatically delete these documents, so the business should define and follow a retention policy.

## Email delivery

Mail delivery is configured through the existing `MAIL_*` environment variables. The default `MAIL_MAILER=log` keeps local development safe; it writes rendered messages to `storage/logs/mail.log` (including verification codes and password-reset links). Keep that log private and do not use the log mailer in production. Customer email verification uses a six-digit code that expires after five minutes and is invalidated after five incorrect attempts; password recovery continues to use a one-time reset link. Verification and password-reset emails use a simple FleetGuard-branded template; booking and other notification emails keep their existing template. Verification and password-reset notifications send synchronously in local development and whenever `QUEUE_CONNECTION=sync`; in other environments they are queued after the account transaction commits. Booking emails to customers and new-booking alerts to admins/managers send synchronously in local web requests and whenever `QUEUE_CONNECTION=sync`; otherwise they are queued after the booking transaction commits. New-booking alerts go to valid email addresses on user accounts with the admin or manager role. When `QUEUE_CONNECTION` is not `sync`, run `php artisan queue:work` in a separate terminal for queued email delivery. The mail-test command sends directly through the mail transport and reports whether a queue worker process is detected; a successful test email does not verify that queued messages are being processed.

For safe delivery testing, use a sandbox SMTP inbox and only its test recipients. Set `MAIL_MAILER=smtp`, the sandbox's `MAIL_HOST` and `MAIL_PORT`, `MAIL_SCHEME` (`smtp` for the provider's STARTTLS setup or `smtps` for implicit TLS), `MAIL_USERNAME`, `MAIL_PASSWORD`, and a sandbox `MAIL_FROM_ADDRESS`. `MAIL_FROM_NAME` defaults to FleetGuard. Run `php artisan fleetguard:mail-test sandbox-recipient@example.test` to send a branded test message directly through the configured mail transport. The address argument is required.

For go-live, use a transactional email service, set the SMTP credentials only in the deployment environment, use a from-address on the business domain, and configure SPF, DKIM, and DMARC for that domain. If the queue connection is not `sync`, keep a worker running under a process manager such as Supervisor or systemd; a one-off `php artisan queue:work` is suitable for local development, not as the only production worker. Do not commit credentials or use a real customer's address for a test send. Delivery failures are logged without exception messages, credentials, full recipient addresses, or message URLs and do not roll back a booking or status transition.

## Public website configuration

The public FleetGuard site includes the fleet showcase, booking-request steps, About, FAQ, and Contact sections. The fleet inventory, images, rates, and availability are sample/unverified unless they are configured as live vehicle records; availability and rates are confirmed by FleetGuard. Booking requests are reviewed by the team and do not take payment.

Set public business details in the environment using `FLEETGUARD_PHONE`, `FLEETGUARD_EMAIL`, `FLEETGUARD_ADDRESS`, `FLEETGUARD_BUSINESS_HOURS`, and `FLEETGUARD_MAPS_URL`. Optional social profile URLs use `FLEETGUARD_SOCIAL_FACEBOOK`, `FLEETGUARD_SOCIAL_INSTAGRAM`, and `FLEETGUARD_SOCIAL_TIKTOK`. All values are optional. When unset, the site displays “To be added”; map and social links are rendered only for HTTP or HTTPS URLs. These settings are read from `config/fleetguard.php`.

When serving the site through an HTTPS tunnel or reverse proxy, Laravel trusts forwarded host and protocol headers only from `TRUSTED_PROXIES` (loopback addresses by default for local development). Set this to the proxy addresses that connect directly to Laravel; do not use `*` on a publicly reachable deployment.

The public navbar links to section anchors on the home page and to the corresponding home-page anchor from other customer-facing pages. Customer account actions reflect sign-in and email-verification state; administrator and manager sessions are not given a public admin-portal link.

## Testimonials foundation

Administrators can manage customer reviews in **Admin > Customer Reviews**. Manual entries are saved as drafts and appear on the homepage only after they are published. No sample testimonials are seeded. Optional manual profile images are stored on Laravel's public disk; run `php artisan storage:link` in each environment that serves uploaded images.

Customers with verified accounts can submit one review for each booking whose status is `completed`. Submissions require consent to display the review under the customer's first name and last initial, start in pending moderation, and use initials rather than profile images. Customers cannot edit or resubmit a review after submission (including rejected reviews); administrators may edit pending content before publishing or rejecting it. Google review synchronization is not included.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
