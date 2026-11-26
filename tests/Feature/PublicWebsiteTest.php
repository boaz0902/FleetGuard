<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\LegalPolicy;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PublicWebsiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_assets_use_https_forwarded_by_a_trusted_tunnel_proxy(): void
    {
        $this->withHeaders([
            'X-Forwarded-Host' => 'fleetguard-tunnel.test',
            'X-Forwarded-Proto' => 'https',
        ])->get('/')
            ->assertOk()
            ->assertSee('https://fleetguard-tunnel.test/build/assets/', false)
            ->assertDontSee('http://localhost/build/assets/', false);
    }

    public function test_homepage_has_distinct_sections_and_navigation_targets_in_order(): void
    {
        $response = $this->get(route('home'))->assertOk();

        $response->assertSeeInOrder([
            'id="top"',
            'id="fleet"',
            'id="how-it-works"',
            'id="booking"',
            'id="about"',
            'id="faq"',
            'id="contact"',
        ], false);
        $response->assertSee('href="#fleet"', false);
        $response->assertSee('href="#how-it-works"', false);
        $response->assertSee('href="#about"', false);
        $response->assertSee('href="#faq"', false);
        $response->assertSee('href="#contact"', false);
        $response->assertSee('href="#booking"', false);
        $response->assertSee('Legal & Policies');
        foreach ([
            'terms-and-conditions',
            'privacy-policy',
            'rental-policy',
            'cancellation-policy',
            'refund-policy',
            'late-return-policy',
            'damage-accident-policy',
        ] as $slug) {
            $response->assertSee('href="'.route('legal.show', $slug).'"', false);
        }
        $response->assertSee('I have read and agree to the');
        $response->assertSee('I acknowledge the');
        $response->assertSee('id="vehicle-search"', false);
        $response->assertDontSee('id="experience"', false);
        $response->assertSee('How to Rent a Car with FleetGuard');
        $response->assertSee('To be confirmed by FleetGuard.');
    }

    public function test_customer_pages_have_one_meaningful_h1_and_no_skipped_heading_levels(): void
    {
        $vehicle = Vehicle::query()->create([
            'id' => 'heading-audit-car',
            'name' => 'Heading Audit Car',
            'brand' => 'FleetGuard',
            'model' => 'Audit',
            'category' => 'sedan',
            'description' => 'A vehicle for heading structure checks.',
            'daily_rate' => 1800,
            'passenger_capacity' => 5,
            'transmission' => 'Automatic',
            'fuel_type' => 'Gasoline',
            'status' => Vehicle::STATUS_ACTIVE,
        ]);

        foreach ([
            $this->get(route('home')),
            $this->get(route('fleet')),
            $this->get(route('fleet.show', $vehicle)),
            $this->get(route('booking')),
            $this->get(route('login')),
            $this->get(route('register')),
            $this->get(route('password.request')),
            $this->get(route('legal.show', 'terms-and-conditions')),
        ] as $response) {
            $this->assertCustomerHeadingStructure($response->assertOk()->getContent());
        }

        $policy = LegalPolicy::query()->where('slug', 'terms-and-conditions')->firstOrFail();
        $policy->versions()->latest('id')->firstOrFail()->update([
            'content' => '<h1>Policy content heading</h1><h3>Policy subheading</h3>',
        ]);
        $this->assertCustomerHeadingStructure(
            $this->get(route('legal.show', $policy->slug))->assertOk()->getContent(),
        );

        $this->assertCustomerHeadingStructure(
            $this->get('/heading-audit-missing-page')->assertNotFound()->getContent(),
        );

        $user = $this->makeUser('customer');
        $customer = Customer::query()->forceCreate([
            'user_id' => $user->id,
            'full_name' => $user->name,
            'email' => $user->email,
            'mobile_number' => '09171234567',
            'status' => Customer::STATUS_ACTIVE,
        ]);
        $booking = Booking::query()->create([
            'booking_reference' => 'FG-HEADING-AUDIT',
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'pickup_location' => 'Panabo City',
            'pickup_datetime' => now()->addDays(2),
            'return_location' => 'Panabo City',
            'return_datetime' => now()->addDays(4),
            'rental_duration_days' => 2,
            'daily_rate_snapshot' => 1800,
            'estimated_total' => 3600,
            'status' => Booking::STATUS_PENDING,
        ]);
        $completedBooking = $booking->replicate();
        $completedBooking->booking_reference = 'FG-HEADING-REVIEW';
        $completedBooking->status = Booking::STATUS_COMPLETED;
        $completedBooking->save();

        $this->actingAs($user);

        foreach ([
            $this->get(route('account')),
            $this->get(route('account.profile')),
            $this->get(route('account.bookings')),
            $this->get(route('account.legal-policies')),
            $this->get(route('bookings.show', $booking)),
            $this->get(route('account.bookings.testimonials.create', $completedBooking)),
        ] as $response) {
            $this->assertCustomerHeadingStructure($response->assertOk()->getContent());
        }

        $unverifiedUser = $this->makeUser('customer', false);
        $this->actingAs($unverifiedUser);
        $this->assertCustomerHeadingStructure(
            $this->get(route('verification.notice'))->assertOk()->getContent(),
        );
    }

    public function test_customer_fleet_uses_active_database_vehicles_and_loads_schedule_in_the_modal(): void
    {
        Vehicle::query()->create([
            'id' => 'fleetguard-vios',
            'name' => 'FleetGuard Vios',
            'brand' => 'Toyota',
            'model' => 'Vios',
            'category' => 'sedan',
            'description' => 'A fleet vehicle.',
            'daily_rate' => '2500.00',
            'passenger_capacity' => 5,
            'transmission' => 'Automatic',
            'fuel_type' => 'Gasoline',
            'image_path' => null,
            'status' => Vehicle::STATUS_ACTIVE,
        ]);
        Vehicle::query()->create([
            'id' => 'maintenance-car',
            'name' => 'Maintenance Car',
            'brand' => 'Toyota',
            'model' => 'Service',
            'category' => 'sedan',
            'daily_rate' => '2000.00',
            'passenger_capacity' => 5,
            'transmission' => 'Automatic',
            'fuel_type' => 'Gasoline',
            'status' => Vehicle::STATUS_MAINTENANCE,
        ]);
        Vehicle::query()->create([
            'id' => 'inactive-car',
            'name' => 'Inactive Car',
            'brand' => 'Toyota',
            'model' => 'Inactive',
            'category' => 'sedan',
            'daily_rate' => '2000.00',
            'passenger_capacity' => 5,
            'transmission' => 'Automatic',
            'fuel_type' => 'Gasoline',
            'status' => Vehicle::STATUS_INACTIVE,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('FleetGuard Vios')
            ->assertSee('data-schedule-url="'.route('vehicles.schedule', 'fleetguard-vios').'"', false)
            ->assertSee('Availability schedule')
            ->assertSee('data-dialog-schedule-status', false)
            ->assertSee('Current rate')
            ->assertSee('Maintenance Car')
            ->assertDontSee('Inactive Car')
            ->assertDontSee('Sample inventory')
            ->assertViewHas('vehicles', fn ($vehicles) => $vehicles->count() === 2);
    }

    public function test_public_navigation_and_footer_follow_customer_and_staff_account_state(): void
    {
        $guestResponse = $this->get(route('home'))->assertOk();
        $guestResponse->assertSee('Sign in');
        $guestResponse->assertSee('Create account');
        $guestResponse->assertDontSee('href="'.route('account.bookings').'"', false);
        $this->assertNoAdminLinks($guestResponse);

        $unverifiedCustomer = $this->makeUser('customer', false);
        $unverifiedResponse = $this->actingAs($unverifiedCustomer)->get(route('home'))->assertOk();
        $unverifiedResponse->assertSee('My account');
        $unverifiedResponse->assertSee('Sign out');
        $unverifiedResponse->assertDontSee('href="'.route('account.bookings').'"', false);
        $this->assertNoAdminLinks($unverifiedResponse);

        $verifiedCustomer = $this->makeUser('customer');
        $verifiedResponse = $this->actingAs($verifiedCustomer)->get(route('home'))->assertOk();
        $verifiedResponse->assertSee('My Bookings');
        $verifiedResponse->assertSee(route('account.bookings'), false);
        $this->assertNoAdminLinks($verifiedResponse);

        foreach (['admin', 'manager'] as $role) {
            $staffResponse = $this->actingAs($this->makeUser($role))->get(route('home'))->assertOk();
            $staffResponse->assertSee('Sign out');
            $staffResponse->assertDontSee('Admin portal');
            $staffResponse->assertDontSee('My account');
            $this->assertNoAdminLinks($staffResponse);
            $staffResponse->assertSee('action="'.route('admin.logout').'"', false);
        }
    }

    public function test_navigation_from_customer_pages_uses_home_section_urls(): void
    {
        $response = $this->get(route('login'))->assertOk();

        foreach (['fleet', 'how-it-works', 'about', 'faq', 'contact', 'booking'] as $section) {
            $response->assertSee('href="'.route('home').'#'.$section.'"', false);
        }

        $this->assertNoAdminLinks($response);
    }

    public function test_contact_details_use_placeholders_until_safe_values_are_configured(): void
    {
        config([
            'fleetguard.phone' => null,
            'fleetguard.email' => null,
            'fleetguard.address' => null,
            'fleetguard.business_hours' => null,
            'fleetguard.maps_url' => null,
            'fleetguard.social.facebook' => null,
            'fleetguard.social.instagram' => null,
            'fleetguard.social.tiktok' => null,
            'fleetguard.social.x' => null,
            'fleetguard.social.youtube' => null,
        ]);

        $placeholderResponse = $this->get(route('home'))->assertOk();
        $placeholderResponse->assertSee('To be added');
        $placeholderResponse->assertDontSee('Open in Maps');
        $placeholderResponse->assertDontSee('public-social-links');

        config([
            'fleetguard.phone' => '+63 912 345 6789',
            'fleetguard.email' => 'hello@fleetguard.example',
            'fleetguard.address' => 'Panabo City, Philippines',
            'fleetguard.business_hours' => 'Monday to Saturday, 8:00 AM to 5:00 PM',
            'fleetguard.maps_url' => 'https://www.google.com/maps/place/fleetguard',
            'fleetguard.social.facebook' => 'https://www.facebook.com/fleetguard',
            'fleetguard.social.instagram' => 'javascript:alert(1)',
            'fleetguard.social.tiktok' => null,
        ]);

        $configuredResponse = $this->get(route('home'))->assertOk();
        $configuredResponse->assertSee('href="tel:+639123456789"', false);
        $configuredResponse->assertSee('href="mailto:hello@fleetguard.example"', false);
        $configuredResponse->assertSee('Panabo City, Philippines');
        $configuredResponse->assertSee('Monday to Saturday, 8:00 AM to 5:00 PM');
        $configuredResponse->assertSee('href="https://www.google.com/maps?q=fleetguard"', false);
        $configuredResponse->assertSee('FleetGuard on Facebook, @fleetguard');
        $configuredResponse->assertSee('@fleetguard');
        $configuredResponse->assertSee('title="FleetGuard location map"', false);
        $configuredResponse->assertSee('loading="lazy"', false);
        $configuredResponse->assertDontSee('javascript:alert(1)');

        config(['fleetguard.maps_url' => 'javascript:alert(2)']);
        $unsafeMapResponse = $this->get(route('home'))->assertOk();
        $unsafeMapResponse->assertDontSee('javascript:alert(2)');
        $unsafeMapResponse->assertDontSee('Open in Maps');

        config(['fleetguard.maps_url' => 'https://maps.app.goo.gl/previewShortLink']);
        $shortMapResponse = $this->get(route('home'))->assertOk();
        $shortMapResponse->assertSee('Open in Maps')
            ->assertDontSee('title="FleetGuard location map"', false)
            ->assertDontSee('does not support an embedded preview')
            ->assertDontSee('Add a full Google Maps link to show a live map.');
    }

    public function test_social_links_use_the_shared_component_for_all_six_platforms(): void
    {
        config([
            'fleetguard.phone' => null,
            'fleetguard.text_number' => null,
            'fleetguard.social.facebook' => '@FleetGuard',
            'fleetguard.social.instagram' => '@fleetguardlonghandle123456789',
            'fleetguard.social.tiktok' => '@fleetguardvisualqa',
            'fleetguard.social.x' => '@FleetGuardQA',
            'fleetguard.social.youtube' => '@FleetGuardPreviewChannel123456',
            'fleetguard.social.messenger' => 'https://m.me/FleetGuardLongPageName',
        ]);

        $response = $this->get(route('home'))->assertOk();
        $content = $response->getContent();

        foreach (['facebook', 'instagram', 'tiktok', 'twitter-x', 'youtube', 'messenger'] as $icon) {
            $response->assertSee('bi-'.$icon, false);
        }

        $response->assertSee('public-social-links--footer', false)
            ->assertSee('public-social-links--card', false)
            ->assertSee('FleetGuard on Facebook, @FleetGuard')
            ->assertSee('FleetGuard on Instagram, @fleetguardlonghandle123456789')
            ->assertSee('FleetGuard on TikTok, @fleetguardvisualqa')
            ->assertSee('FleetGuard on X, @FleetGuardQA')
            ->assertSee('FleetGuard on YouTube, @FleetGuardPreviewChannel123456')
            ->assertSee('FleetGuard on Messenger, @FleetGuardLongPageName')
            ->assertSee('target="_blank" rel="noopener noreferrer"', false);

        $this->assertSame(12, substr_count($content, 'class="public-social-link"'));
    }

    public function test_social_registry_extension_renders_without_partial_markup_changes(): void
    {
        config([
            'fleetguard.social_platforms.linkedin' => [
                'name' => 'LinkedIn',
                'icon' => 'linkedin',
                'hosts' => ['linkedin.com', 'www.linkedin.com'],
                'base' => 'https://www.linkedin.com/in/',
                'label' => 'LinkedIn profile',
                'pattern' => '/^[A-Za-z0-9-]{3,30}$/',
            ],
            'fleetguard.social.linkedin' => '@FleetGuardQA',
        ]);

        $response = $this->get(route('home'))->assertOk();

        $response->assertSee('bi-linkedin', false)
            ->assertSee('FleetGuard on LinkedIn, @FleetGuardQA')
            ->assertSee('href="https://www.linkedin.com/in/FleetGuardQA"', false);
    }

    public function test_empty_social_links_partial_renders_no_container(): void
    {
        $this->view('partials.social-links', [
            'profiles' => [],
            'variant' => 'footer',
        ])->assertDontSee('public-social-links');
    }

    public function test_business_hours_trim_outer_whitespace_and_preserve_line_breaks(): void
    {
        config(['fleetguard.business_hours' => " \nMonday to Friday: 8:00 AM - 5:00 PM\nSaturday: 9:00 AM - 1:00 PM \n"]);

        $response = $this->get(route('home'))->assertOk();

        $this->assertMatchesRegularExpression(
            '/<dd class="public-business-hours">\s*<span class="public-business-hours-value">Monday to Friday: 8:00 AM - 5:00 PM\nSaturday: 9:00 AM - 1:00 PM<\/span>/',
            $response->getContent(),
        );
    }

    public function test_public_error_pages_are_branded_and_admin_login_remains_available(): void
    {
        config(['app.debug' => false]);

        $this->get('/page-that-does-not-exist')
            ->assertNotFound()
            ->assertSee('We can\'t find that page.')
            ->assertSee('Back to FleetGuard');

        $this->view('errors.419')
            ->assertSee('This page has expired.')
            ->assertSee('419');
        $this->view('errors.500')
            ->assertSee('We hit a temporary issue.')
            ->assertSee('500');

        $this->get('/admin/page-that-does-not-exist')
            ->assertNotFound()
            ->assertSee('Return to admin sign in')
            ->assertDontSee('public-footer');

        $this->get(route('admin.login'))
            ->assertOk()
            ->assertDontSee('public-footer');
    }

    public function test_public_page_reads_do_not_write_customer_booking_or_audit_records(): void
    {
        $before = [
            Customer::query()->count(),
            Booking::query()->count(),
            AuditLog::query()->count(),
        ];

        foreach ([route('home'), route('login'), route('register'), route('admin.login')] as $url) {
            $this->get($url)->assertOk();
        }

        $this->assertSame($before, [
            Customer::query()->count(),
            Booking::query()->count(),
            AuditLog::query()->count(),
        ]);
    }

    private function makeUser(string $role, bool $verified = true): User
    {
        $user = User::factory()->create();
        $user->forceFill([
            'role' => $role,
            'email_verified_at' => $verified ? now() : null,
        ])->save();

        return $user->fresh();
    }

    private function assertCustomerHeadingStructure(string $html): void
    {
        $document = new \DOMDocument;
        $previousErrorMode = libxml_use_internal_errors(true);

        try {
            $document->loadHTML($html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorMode);
        }

        $headings = [];
        foreach ($document->getElementsByTagName('*') as $element) {
            if (preg_match('/^h([1-6])$/i', $element->tagName, $matches)) {
                $headings[] = [(int) $matches[1], trim(preg_replace('/\s+/u', ' ', $element->textContent) ?? '')];
            }
        }

        $this->assertNotEmpty($headings, 'Expected the page to render at least one heading.');
        $h1s = array_values(array_filter($headings, fn (array $heading): bool => $heading[0] === 1));
        $this->assertCount(1, $h1s, 'Expected exactly one H1.');
        $this->assertNotSame('', $h1s[0][1], 'Expected the H1 to have meaningful text.');

        $previousLevel = 0;
        foreach ($headings as [$level]) {
            if ($previousLevel === 0) {
                $this->assertSame(1, $level, 'The first page heading should be an H1.');
            } else {
                $this->assertLessThanOrEqual($previousLevel + 1, $level, 'Heading levels must not skip.');
            }

            $previousLevel = $level;
        }
    }

    private function assertNoAdminLinks(TestResponse $response): void
    {
        $this->assertDoesNotMatchRegularExpression(
            '/<a\b[^>]*href=(?:"[^"]*\/admin[^"]*"|\'[^\']*\/admin[^\']*\')/i',
            $response->getContent(),
        );
    }
}
