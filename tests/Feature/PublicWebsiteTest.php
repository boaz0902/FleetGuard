<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PublicWebsiteTest extends TestCase
{
    use RefreshDatabase;

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
        $response->assertSee('id="vehicle-search"', false);
        $response->assertDontSee('id="experience"', false);
        $response->assertSee('A clear path from search to request.');
        $response->assertSee('To be confirmed by FleetGuard.');
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
        ]);

        $placeholderResponse = $this->get(route('home'))->assertOk();
        $placeholderResponse->assertSee('To be added');
        $placeholderResponse->assertDontSee('Open in Maps');
        $placeholderResponse->assertSee('Social links');

        config([
            'fleetguard.phone' => '+63 912 345 6789',
            'fleetguard.email' => 'hello@fleetguard.example',
            'fleetguard.address' => 'Panabo City, Philippines',
            'fleetguard.business_hours' => 'Monday to Saturday, 8:00 AM to 5:00 PM',
            'fleetguard.maps_url' => 'https://maps.example.test/fleetguard',
            'fleetguard.social.facebook' => 'https://social.example.test/fleetguard',
            'fleetguard.social.instagram' => 'javascript:alert(1)',
            'fleetguard.social.tiktok' => null,
        ]);

        $configuredResponse = $this->get(route('home'))->assertOk();
        $configuredResponse->assertSee('href="tel:+639123456789"', false);
        $configuredResponse->assertSee('href="mailto:hello@fleetguard.example"', false);
        $configuredResponse->assertSee('Panabo City, Philippines');
        $configuredResponse->assertSee('Monday to Saturday, 8:00 AM to 5:00 PM');
        $configuredResponse->assertSee('href="https://maps.example.test/fleetguard"', false);
        $configuredResponse->assertSee('FleetGuard on Facebook');
        $configuredResponse->assertDontSee('javascript:alert(1)');

        config(['fleetguard.maps_url' => 'javascript:alert(2)']);
        $unsafeMapResponse = $this->get(route('home'))->assertOk();
        $unsafeMapResponse->assertDontSee('javascript:alert(2)');
        $unsafeMapResponse->assertDontSee('Open in Maps');
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

    private function assertNoAdminLinks(TestResponse $response): void
    {
        $this->assertDoesNotMatchRegularExpression(
            '/<a\b[^>]*href=(?:"[^"]*\/admin[^"]*"|\'[^\']*\/admin[^\']*\')/i',
            $response->getContent(),
        );
    }
}
