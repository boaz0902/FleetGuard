<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\LegalPolicy;
use App\Models\LegalPolicyAcceptance;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\LegalPolicyContent;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class LegalPoliciesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-18 08:00:00');

        Vehicle::query()->create([
            'id' => 'legal-test-car',
            'name' => 'Legal Test Car',
            'brand' => 'FleetGuard',
            'model' => 'Test',
            'category' => 'sedan',
            'description' => 'Legal module test vehicle.',
            'daily_rate' => 1800,
            'passenger_capacity' => 5,
            'transmission' => 'Automatic',
            'fuel_type' => 'Gasoline',
            'image_path' => null,
            'status' => Vehicle::STATUS_ACTIVE,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_published_policies_are_public_but_unpublished_policies_are_not(): void
    {
        $this->get(route('legal.show', 'terms-and-conditions'))
            ->assertOk()
            ->assertSee('Terms &amp; Conditions')
            ->assertSee('DRAFT')
            ->assertSee('Pending confirmation');

        $policy = LegalPolicy::query()->where('slug', 'terms-and-conditions')->firstOrFail();
        $this->actingAs($this->userWithRole('admin'))
            ->post(route('admin.legal-policies.unpublish', $policy))
            ->assertRedirect(route('admin.legal-policies.edit', $policy));

        $this->get(route('legal.show', 'terms-and-conditions'))->assertNotFound();
        $this->get(route('admin.legal-policies.index'))->assertOk();
    }

    public function test_current_published_version_at_uses_the_manila_effective_date(): void
    {
        $policy = LegalPolicy::query()->where('slug', 'terms-and-conditions')->firstOrFail();
        $policy->versions()->whereNull('effective_at')->update([
            'published_at' => '2026-11-17 00:00:00',
        ]);
        $futureVersion = $policy->versions()->create([
            'version' => '2.0',
            'content' => 'A reviewed future policy version.',
            'status' => 'published',
            'effective_at' => '2026-11-19',
            'published_at' => now(),
        ]);

        $this->assertNotSame(
            $futureVersion->id,
            $policy->currentPublishedVersionAt(Carbon::parse('2026-11-18 15:59:00', 'UTC'))?->id,
        );
        $this->assertSame(
            $futureVersion->id,
            $policy->currentPublishedVersionAt(Carbon::parse('2026-11-18 16:00:00', 'UTC'))?->id,
        );
    }

    public function test_booking_requires_all_agreements_and_records_each_exact_version(): void
    {
        $payload = $this->bookingPayload();
        unset($payload['agreements']['refund-policy']);

        $this->postJson(route('bookings.store'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('agreements.refund-policy');

        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('legal_policy_acceptances', 0);

        $payload = $this->bookingPayload();
        $response = $this->postJson(route('bookings.store'), $payload);
        $response->assertCreated();

        $booking = Booking::query()->firstOrFail();
        $acceptances = LegalPolicyAcceptance::query()->where('booking_id', $booking->id)->get();
        $this->assertCount(4, $acceptances);

        foreach (LegalPolicy::bookingAgreementSlugs() as $slug) {
            $policy = LegalPolicy::query()->where('slug', $slug)->firstOrFail();
            $acceptance = $acceptances->firstWhere('legal_policy_id', $policy->id);
            $this->assertNotNull($acceptance);
            $this->assertSame((int) $payload['policy_versions'][$slug], $acceptance->legal_policy_version_id);
            $this->assertSame('accepted', $acceptance->status);
            $this->assertNotNull($acceptance->accepted_at);
        }

        $acceptanceToUpdate = $acceptances->first();
        $this->assertNotNull($acceptanceToUpdate);
        $this->expectException(LogicException::class);
        $acceptanceToUpdate->update(['status' => 'rejected']);
    }

    public function test_stale_policy_versions_cannot_be_accepted_and_published_history_is_preserved(): void
    {
        $payload = $this->bookingPayload();
        $policy = LegalPolicy::query()->where('slug', 'terms-and-conditions')->firstOrFail();
        $policy->versions()->whereNull('effective_at')->update([
            'published_at' => '2026-11-17 00:00:00',
        ]);
        $original = $policy->currentPublishedVersion;

        $this->actingAs($this->userWithRole('admin'))
            ->put(route('admin.legal-policies.update', $policy), [
                'content' => "## Updated terms\n\nA new reviewed policy version with clearly stated, approved information.",
                'effective_at' => '2026-11-18',
            ])
            ->assertRedirect(route('admin.legal-policies.edit', $policy));

        $draft = $policy->versions()->latest('id')->firstOrFail();
        $this->assertSame('draft', $draft->status);
        $this->assertSame('1.0-draft', $original->fresh()->version);

        $this->actingAs($this->userWithRole('admin'))
            ->post(route('admin.legal-policies.publish', [$policy, $draft]), ['effective_at' => '2026-11-18'])
            ->assertRedirect(route('admin.legal-policies.edit', $policy));

        $this->postJson(route('bookings.store'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('agreements.terms-and-conditions');
        $this->assertDatabaseCount('bookings', 0);

        $payload = $this->bookingPayload();
        $this->postJson(route('bookings.store'), $payload)->assertCreated();
        $booking = Booking::query()->firstOrFail();

        $this->assertSame(
            (int) $payload['policy_versions']['terms-and-conditions'],
            $booking->legalPolicyAcceptances()->where('legal_policy_id', $policy->id)->value('legal_policy_version_id'),
        );
        $this->assertSame($original->content, $original->fresh()->content);
        $this->assertSame('published', $original->fresh()->status);
        $this->assertDatabaseCount('legal_policy_versions', 8);
    }

    public function test_publish_uses_the_effective_date_selected_in_the_editor(): void
    {
        $policy = LegalPolicy::query()->where('slug', 'terms-and-conditions')->firstOrFail();
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->put(route('admin.legal-policies.update', $policy), [
                'content' => 'A reviewed policy draft with a confirmed future effective date.',
            ])
            ->assertRedirect(route('admin.legal-policies.edit', $policy));

        $draft = $policy->versions()->latest('id')->firstOrFail();
        $this->actingAs($admin)
            ->post(route('admin.legal-policies.publish', [$policy, $draft]), [
                'effective_at' => '2026-12-01',
            ])
            ->assertRedirect(route('admin.legal-policies.edit', $policy));

        $this->assertDatabaseHas('legal_policy_versions', [
            'id' => $draft->id,
            'status' => 'published',
            'effective_at' => '2026-12-01',
        ]);
    }

    public function test_policy_formatting_renders_headings_emphasis_and_lists_safely(): void
    {
        $policy = LegalPolicy::query()->where('slug', 'cancellation-policy')->firstOrFail();
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->put(route('admin.legal-policies.update', $policy), [
                'content' => "## Cancellation overview\n\nPlease **review** this *carefully*.\n\n- Before pickup\n- During the rental\n\n1. Contact FleetGuard\n2. Keep a record",
            ])
            ->assertRedirect(route('admin.legal-policies.edit', $policy));

        $draft = $policy->versions()->latest('id')->firstOrFail();
        $this->actingAs($admin)
            ->post(route('admin.legal-policies.publish', [$policy, $draft]), ['effective_at' => '2026-11-18'])
            ->assertRedirect(route('admin.legal-policies.edit', $policy));

        $this->actingAs($admin)
            ->get(route('admin.legal-policies.edit', $policy))
            ->assertOk()
            ->assertSee('data-legal-action="bold"', false)
            ->assertSee('data-legal-state="bold"', false)
            ->assertSee('aria-pressed="false"', false)
            ->assertSee('data-legal-action="italic"', false)
            ->assertSee('data-legal-action="underline"', false)
            ->assertSee('data-legal-action="strikeThrough"', false)
            ->assertSee('data-legal-action="fontName"', false)
            ->assertSee('data-legal-action="fontSize"', false)
            ->assertSee('data-legal-action="foreColor"', false)
            ->assertSee('data-legal-action="hiliteColor"', false)
            ->assertSee('data-legal-action="lineHeight"', false)
            ->assertSee('data-legal-block-style="normal"', false)
            ->assertSee('data-legal-block-style="title"', false)
            ->assertSee('data-legal-block-style="subtitle"', false)
            ->assertSee('data-legal-block-style="heading1"', false)
            ->assertSee('data-legal-block-style="heading2"', false)
            ->assertSee('data-legal-block-style="heading3"', false)
            ->assertSee('data-legal-action="justifyFull"', false)
            ->assertSee('data-legal-action="insertUnorderedList"', false)
            ->assertSee('data-legal-action="insertOrderedList"', false)
            ->assertSee('data-legal-action="indent"', false)
            ->assertSee('data-legal-action="outdent"', false)
            ->assertSee('data-legal-action="removeFormat"', false)
            ->assertSee('Ctrl+B');

        $this->get(route('legal.show', 'cancellation-policy'))
            ->assertOk()
            ->assertSee('<h2>Cancellation overview</h2>', false)
            ->assertSee('<strong>review</strong>', false)
            ->assertSee('<em>carefully</em>', false)
            ->assertSee('<ul>', false)
            ->assertSee('<ol>', false);

        $this->assertSame(
            '**safe**',
            app(LegalPolicyContent::class)->render('<script>alert(1)</script> **safe**')->toHtml(),
        );
    }

    public function test_public_policy_content_headings_follow_the_document_outline(): void
    {
        $content = app(LegalPolicyContent::class)->renderDocument(
            '<h1>Policy section</h1><h3>Subsection</h3><h4>Details</h4><h2>Next section</h2>',
        )->toHtml();

        preg_match_all('/<h([1-6])\b/', $content, $matches);

        $this->assertSame(['2', '3', '4', '2'], $matches[1]);
        $this->assertStringNotContainsString('<h1', $content);
    }

    public function test_rich_text_is_saved_as_formatted_html_and_rendered_with_safe_styles(): void
    {
        $policy = LegalPolicy::query()->where('slug', 'cancellation-policy')->firstOrFail();
        $admin = $this->userWithRole('admin');
        $content = '<h2 style="text-align: center; font-weight: 600">Policy overview</h2><p style="line-height: 2; margin-left: 20px; position: fixed"><strong>Direct bold</strong> <em>italic</em> <u>underline</u> <s>strike</s> <span style="font-family: Arial; font-size: 18pt; color: #ff0000; background-color: #fff19c">styled words</span></p><ul><li>First point</li><li>Second point</li></ul>';

        $this->actingAs($admin)
            ->put(route('admin.legal-policies.update', $policy), [
                'content' => $content,
                'effective_at' => '2026-11-18',
            ])
            ->assertRedirect(route('admin.legal-policies.edit', $policy));

        $draft = $policy->versions()->latest('id')->firstOrFail();
        $this->assertStringNotContainsString('onclick', $draft->content);
        $this->assertStringNotContainsString('position: fixed', $draft->content);
        $this->assertStringContainsString('<strong>Direct bold</strong>', $draft->content);
        $this->assertStringContainsString('font-size: 18pt', $draft->content);
        $this->assertStringContainsString('font-weight: 600', $draft->content);

        $this->actingAs($admin)
            ->post(route('admin.legal-policies.publish', [$policy, $draft]), ['effective_at' => '2026-11-18'])
            ->assertRedirect(route('admin.legal-policies.edit', $policy));

        $this->get(route('legal.show', 'cancellation-policy'))
            ->assertOk()
            ->assertSee('<h2 style="text-align: center; font-weight: 600">Policy overview</h2>', false)
            ->assertSee('<strong>Direct bold</strong>', false)
            ->assertSee('<u>underline</u>', false)
            ->assertSee('<s>strike</s>', false)
            ->assertSee('background-color: #fff19c', false)
            ->assertSee('<li>First point</li>', false);

        $sanitized = app(LegalPolicyContent::class)->render(
            '<p style="color: #ff0000; position: fixed" onclick="alert(1)">Safe <strong>text</strong><script>alert(2)</script></p>',
        )->toHtml();
        $this->assertSame('<p style="color: #ff0000">Safe <strong>text</strong></p>', $sanitized);

        $legacyFont = app(LegalPolicyContent::class)->render('<font face="Arial" size="4" color="#ff0000">legacy</font>')->toHtml();
        $this->assertStringContainsString('font-size: 14pt', $legacyFont);
        $this->assertStringNotContainsString('<font', $legacyFont);

        $indented = app(LegalPolicyContent::class)->render(
            '<blockquote style="margin: 0 0 0 40px; border: none; padding: 0"><p>Indented text</p></blockquote>',
        )->toHtml();
        $this->assertStringContainsString('margin-left: 40px', $indented);
    }

    public function test_only_administrators_manage_policies_and_customers_only_see_their_own_history(): void
    {
        $policy = LegalPolicy::query()->where('slug', 'terms-and-conditions')->firstOrFail();
        $this->actingAs($this->userWithRole('manager'))->get(route('admin.legal-policies.index'))->assertForbidden();
        $this->actingAs($this->userWithRole('staff'))->get(route('admin.legal-policies.index'))->assertForbidden();
        $admin = $this->userWithRole('admin');
        $this->actingAs($admin)->get(route('admin.legal-policies.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.legal-policies.edit', $policy))->assertOk();
        $this->actingAs($admin)
            ->get(route('admin.legal-policies.preview', [$policy, 'version' => $policy->currentPublishedVersion->id]))
            ->assertOk()
            ->assertSee('Administrator preview');
        $this->actingAs($admin)->get(route('admin.legal-policies.acceptances'))->assertOk();
        $this->actingAs($admin)
            ->put(route('admin.legal-policies.update', $policy), [
                'content' => 'This draft contains <script>alert(1)</script> markup and should be rejected.',
            ])
            ->assertSessionHasErrors('content');

        $customerUser = $this->userWithRole('customer');
        $otherUser = $this->userWithRole('customer');
        $customer = Customer::query()->create([
            'user_id' => $customerUser->id,
            'full_name' => 'Policy Customer',
            'email' => $customerUser->email,
            'mobile_number' => '09171234567',
        ]);
        $otherCustomer = Customer::query()->create([
            'user_id' => $otherUser->id,
            'full_name' => 'Other Customer',
            'email' => $otherUser->email,
            'mobile_number' => '09171234568',
        ]);
        $booking = $this->createBooking($customer);
        $otherBooking = $this->createBooking($otherCustomer, 'other');
        $version = $policy->currentPublishedVersion;

        LegalPolicyAcceptance::query()->create([
            'customer_id' => $customer->id,
            'booking_id' => $booking->id,
            'legal_policy_id' => $policy->id,
            'legal_policy_version_id' => $version->id,
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);
        LegalPolicyAcceptance::query()->create([
            'customer_id' => $otherCustomer->id,
            'booking_id' => $otherBooking->id,
            'legal_policy_id' => $policy->id,
            'legal_policy_version_id' => $version->id,
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);

        $this->actingAs($customerUser)
            ->get(route('account.legal-policies'))
            ->assertOk()
            ->assertSee($booking->booking_reference)
            ->assertDontSee($otherBooking->booking_reference)
            ->assertDontSee('Other Customer');
    }

    private function bookingPayload(): array
    {
        $policyVersions = LegalPolicy::query()
            ->whereIn('slug', LegalPolicy::bookingAgreementSlugs())
            ->get()
            ->mapWithKeys(fn (LegalPolicy $policy): array => [$policy->slug => $policy->currentPublishedVersion?->id])
            ->all();

        return [
            'vehicle_id' => 'legal-test-car',
            'full_name' => 'Legal Test Customer',
            'email' => 'legal-customer@example.test',
            'mobile_number' => '09171234567',
            'pickup_location' => 'Panabo City',
            'pickup_date' => '2026-12-10',
            'pickup_time' => '10:00',
            'return_location' => 'Panabo City',
            'return_date' => '2026-12-11',
            'return_time' => '10:00',
            'agreements' => array_fill_keys(LegalPolicy::bookingAgreementSlugs(), true),
            'policy_versions' => $policyVersions,
        ];
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();

        return $user->fresh();
    }

    private function createBooking(Customer $customer, string $suffix = 'owned'): Booking
    {
        return Booking::query()->create([
            'booking_reference' => 'FG-LEGAL-'.$suffix,
            'customer_id' => $customer->id,
            'vehicle_id' => 'legal-test-car',
            'pickup_location' => 'Panabo City',
            'pickup_datetime' => '2026-12-10 10:00:00',
            'return_location' => 'Panabo City',
            'return_datetime' => '2026-12-11 10:00:00',
            'rental_duration_days' => 1,
            'daily_rate_snapshot' => 1800,
            'estimated_total' => 1800,
            'status' => Booking::STATUS_PENDING,
        ]);
    }
}
