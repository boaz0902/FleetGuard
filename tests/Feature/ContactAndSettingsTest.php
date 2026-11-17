<?php

namespace Tests\Feature;

use App\Mail\FleetGuardMessageMail;
use App\Models\AuditLog;
use App\Models\ContactMessage;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\MapLink;
use App\Services\SiteSettings;
use App\Services\SocialProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Tests\TestCase;

class ContactAndSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_submission_creates_a_message_and_sends_both_emails(): void
    {
        Mail::fake();
        config(['fleetguard.email' => 'hello@fleetguard.test', 'mail.from.address' => 'hello@fleetguard.test']);

        $response = $this->from(route('home'))->post(route('contact.store'), [
            'name' => 'Alice Example',
            'email' => 'alice@example.com',
            'phone' => '+63 912 345 6789',
            'subject' => 'General question',
            'message' => 'I would like to ask about the available vehicles.',
            'submitted_at' => (string) (microtime(true) - 5),
        ]);

        $response->assertRedirect(route('home').'#contact');
        $this->assertDatabaseHas('contact_messages', [
            'email' => 'alice@example.com',
            'subject' => 'General question',
            'status' => ContactMessage::STATUS_NEW,
        ]);
        $this->assertSame(1, ContactMessage::query()->count());
        Mail::assertSentCount(2);
        Mail::assertSent(FleetGuardMessageMail::class, fn (FleetGuardMessageMail $mail) => $mail->subjectLine === 'We received your message');
    }

    public function test_honeypot_and_rate_limits_are_rejected(): void
    {
        $this->from(route('home'))->post(route('contact.store'), [
            'name' => 'Bot User',
            'email' => 'bot@example.com',
            'subject' => 'General question',
            'message' => 'Hidden field test.',
            'honeypot' => 'filled',
            'submitted_at' => (string) (microtime(true) - 5),
        ])->assertSessionHasErrors('honeypot');

        for ($i = 0; $i < 3; $i++) {
            $this->from(route('home'))->post(route('contact.store'), [
                'name' => 'Rate Limit',
                'email' => 'limit-'.($i + 1).'@example.com',
                'subject' => 'General question',
                'message' => 'This is a test message.',
                'submitted_at' => (string) (microtime(true) - 5),
            ]);
        }

        $this->from(route('home'))->post(route('contact.store'), [
            'name' => 'Rate Limit',
            'email' => 'limit-1@example.com',
            'subject' => 'General question',
            'message' => 'This is a test message.',
            'submitted_at' => (string) (microtime(true) - 5),
        ])->assertSessionHasNoErrors();
    }

    public function test_site_settings_override_default_public_config(): void
    {
        SiteSetting::query()->create([
            'key' => 'phone',
            'value' => '+63 999 111 2222',
        ]);

        app(SiteSettings::class)->applyToConfig();

        $this->assertSame('+63 999 111 2222', config('fleetguard.phone'));
    }

    public function test_settings_page_is_view_only_for_managers_and_editable_for_admins(): void
    {
        $manager = User::factory()->create();
        $manager->forceFill(['role' => 'manager'])->save();

        $managerResponse = $this->actingAs($manager)->get(route('admin.settings.index'));
        $managerResponse->assertOk()
            ->assertSee('Only administrators can change these details.')
            ->assertDontSee('Save settings');
        $this->assertMatchesRegularExpression('/<input[^>]*name="email"[^>]*disabled/', $managerResponse->getContent());

        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();

        $adminResponse = $this->actingAs($admin)->get(route('admin.settings.index'));
        $adminResponse->assertOk()->assertSee('Save settings');
        $this->assertDoesNotMatchRegularExpression('/<input[^>]*name="email"[^>]*disabled/', $adminResponse->getContent());
    }

    public function test_settings_save_flattens_all_social_values_and_public_pages_render_them(): void
    {
        $admin = $this->userWithRole('admin');
        $response = $this->actingAs($admin)
            ->from(route('admin.settings.index'))
            ->post(route('admin.settings.update'), $this->completeSettingsPayload());

        $response->assertRedirect(route('admin.settings.index'))
            ->assertSessionHas('success', 'Business details updated.');

        $expectedSocial = [
            'social_facebook' => 'https://www.facebook.com/fleetguard',
            'social_instagram' => 'https://www.instagram.com/fleet.guard/',
            'social_tiktok' => 'https://www.tiktok.com/@fleetguard',
            'social_x' => 'https://x.com/fleet_guard',
            'social_youtube' => 'https://www.youtube.com/@FleetGuard',
            'social_messenger' => 'https://m.me/FleetGuard',
        ];

        foreach ($expectedSocial as $key => $value) {
            $setting = SiteSetting::query()->where('key', $key)->firstOrFail();
            $this->assertIsString($setting->value);
            $this->assertSame($value, $setting->value);
        }

        $this->assertDatabaseHas('site_settings', [
            'key' => 'maps_url',
            'value' => 'https://www.google.com/maps/embed?pb=!1m0&key=fleetguard',
        ]);
        $this->assertDatabaseHas('site_settings', [
            'key' => 'text_number',
            'value' => '+639123456789',
        ]);
        $this->assertDatabaseMissing('site_settings', ['key' => 'social']);
        $this->assertDatabaseMissing('site_settings', ['key' => 'social.facebook']);

        $audit = AuditLog::query()->where('action', 'site_settings_updated')->latest('id')->firstOrFail();
        $this->assertArrayHasKey('social_facebook', $audit->changes);
        foreach ($audit->changes as $key => $change) {
            $this->assertStringNotContainsString('.', $key);
            $this->assertTrue($change['to'] === null || is_string($change['to']));
        }

        app(SiteSettings::class)->applyToConfig();
        $this->get(route('home'))->assertOk()
            ->assertSee('FleetGuard Mobility')
            ->assertSee('title="FleetGuard location map"', false)
            ->assertSee('FleetGuard on Instagram, @fleet.guard')
            ->assertSee('FleetGuard on X, @fleet_guard')
            ->assertSee('@FleetGuard')
            ->assertSee('href="sms:+639123456789"', false)
            ->assertSee('aria-label="Text FleetGuard at +63 912 345 6789"', false);

        $this->get(route('admin.settings.index'))->assertOk()
            ->assertSee('data-settings-preview="social"', false)
            ->assertSee('public-social-links--card', false)
            ->assertSee('FleetGuard on Instagram, @fleet.guard')
            ->assertSee('FleetGuard on Messenger, @FleetGuard')
            ->assertSee('Shown to visitors on phones only.')
            ->assertSee('target="_blank" rel="noopener noreferrer"', false);
    }

    public function test_settings_invalid_values_redirect_with_inline_errors_instead_of_500s(): void
    {
        $admin = $this->userWithRole('admin');
        $cases = [
            ['name', str_repeat('x', 256), 'name'],
            ['name', '<script>alert(1)</script>', 'name'],
            ['address', "Panabo\0City", 'address'],
            ['maps_url', 'javascript:alert(1)', 'maps_url'],
            ['maps_url', 'https://user:pass@www.google.com/maps?q=Panabo', 'maps_url'],
            ['maps_url', 'https://www.google.com:8443/maps?q=Panabo', 'maps_url'],
            ['social.instagram', 'https://facebook.com/fleetguard', 'social.instagram'],
            ['social.youtube', 'data:text/html,channel', 'social.youtube'],
            ['social.facebook', str_repeat('@', 501), 'social.facebook'],
            ['name', ['unexpected' => 'array'], 'name'],
        ];

        foreach ($cases as [$field, $value, $errorKey]) {
            $payload = $this->completeSettingsPayload();
            data_set($payload, $field, $value);

            $this->actingAs($admin)
                ->from(route('admin.settings.index'))
                ->post(route('admin.settings.update'), $payload)
                ->assertRedirect(route('admin.settings.index'))
                ->assertSessionHasErrors($errorKey);
        }

        $whitespacePayload = $this->completeSettingsPayload();
        $whitespacePayload['maps_url'] = '   ';
        $this->actingAs($admin)
            ->from(route('admin.settings.index'))
            ->post(route('admin.settings.update'), $whitespacePayload)
            ->assertRedirect(route('admin.settings.index'))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('site_settings', ['key' => 'maps_url', 'value' => null]);
    }

    public function test_settings_write_guard_rejects_nested_values_before_storage(): void
    {
        $admin = $this->userWithRole('admin');

        try {
            app(SiteSettings::class)->update(['social' => ['facebook' => '@fleetguard']], $admin);
            $this->fail('Nested setting values should be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('flat strings or null', $exception->getMessage());
        }

        $this->assertDatabaseCount('site_settings', 0);
    }

    public function test_settings_preview_is_admin_only_and_returns_shared_parser_results(): void
    {
        $manager = $this->userWithRole('manager');
        $this->actingAs($manager)
            ->postJson(route('admin.settings.preview'), ['maps_url' => 'https://www.google.com/maps?q=Panabo'])
            ->assertForbidden();

        $admin = $this->userWithRole('admin');
        $this->actingAs($admin)
            ->postJson(route('admin.settings.preview'), [
                'address' => 'Panabo City',
                'maps_url' => 'https://www.google.com/maps/place/Panabo+City',
                'social' => [
                    'facebook' => '@fleetguard',
                    'instagram' => 'https://instagram.com/fleet.guard/?utm_source=test',
                    'tiktok' => '',
                    'x' => '@fleet_guard',
                    'youtube' => '@FleetGuard',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('map.embed_url', 'https://www.google.com/maps?q=Panabo%20City&output=embed')
            ->assertJsonPath('social.instagram.username', 'fleet.guard')
            ->assertJsonPath('social.tiktok.url', null);

        $this->postJson(route('admin.settings.preview'), [
            'phone' => '09123456789',
            'text_number' => '',
            'social' => ['messenger' => 'm.me/yourpage'],
        ])->assertOk()
            ->assertJsonPath('text_number.number', '+639123456789')
            ->assertJsonPath('social.messenger.url', 'https://m.me/yourpage');

        $this->postJson(route('admin.settings.preview'), [
            'maps_url' => 'https://maps.example.test/place',
            'social' => ['x' => 'https://facebook.com/fleetguard'],
        ])->assertUnprocessable()
            ->assertJsonPath('errors.maps_url.0', "This doesn't look like a Google Maps link.")
            ->assertJsonFragment(['social.x' => ['Enter a valid x profile URL or @handle.']]);
    }

    public function test_short_map_link_guidance_is_shown_in_settings_only(): void
    {
        config(['fleetguard.maps_url' => 'https://maps.app.goo.gl/previewShortLink']);
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('Add a full Google Maps link to show a live map.');

        $manager = $this->userWithRole('manager');
        $this->actingAs($manager)->get(route('admin.settings.index'))
            ->assertOk()
            ->assertDontSee('Add a full Google Maps link to show a live map.')
            ->assertSee('Open in Maps link ready; a live map is unavailable for this link.');

        $this->get(route('home'))->assertOk()
            ->assertDontSee('Add a full Google Maps link to show a live map.');
    }

    public function test_settings_preview_route_is_throttled(): void
    {
        $admin = $this->userWithRole('admin');
        $this->actingAs($admin);

        for ($attempt = 0; $attempt < 30; $attempt++) {
            $this->postJson(route('admin.settings.preview'), ['maps_url' => ''])
                ->assertOk();
        }

        $this->postJson(route('admin.settings.preview'), ['maps_url' => ''])
            ->assertStatus(429);
    }

    public function test_contact_submission_supports_json_success_and_validation_responses(): void
    {
        Mail::fake();
        $this->postJson(route('contact.store'), [
            'name' => 'JSON Sender',
            'email' => 'json@example.test',
            'subject' => 'General question',
            'message' => 'A JSON contact message.',
            'submitted_at' => (string) (microtime(true) - 5),
        ])->assertCreated()
            ->assertJsonStructure(['reference', 'message']);

        $this->postJson(route('contact.store'), [
            'name' => '',
            'email' => 'invalid',
            'subject' => 'Not a valid subject',
            'message' => '',
            'submitted_at' => (string) (microtime(true) - 5),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'subject', 'message']);
    }

    public function test_contact_rate_limit_returns_a_friendly_json_429(): void
    {
        Mail::fake();
        for ($index = 0; $index < 5; $index++) {
            $this->postJson(route('contact.store'), [
                'name' => 'Rate Limited Sender',
                'email' => 'rate-'.$index.'@example.test',
                'subject' => 'General question',
                'message' => 'A rate limit test message.',
                'submitted_at' => (string) (microtime(true) - 5),
            ])->assertCreated();
        }

        $this->postJson(route('contact.store'), [
            'name' => 'Rate Limited Sender',
            'email' => 'rate-last@example.test',
            'subject' => 'General question',
            'message' => 'A rate limit test message.',
            'submitted_at' => (string) (microtime(true) - 5),
        ])->assertStatus(429)
            ->assertJsonPath('message', 'Too many messages were sent recently. Please try again later.');
    }

    public function test_contact_rate_limit_redirect_preserves_retry_fields_without_spam_tokens(): void
    {
        Mail::fake();

        for ($index = 0; $index < 5; $index++) {
            $this->post(route('contact.store'), [
                'name' => 'Rate Limited Sender',
                'email' => 'redirect-rate-'.$index.'@example.test',
                'subject' => 'General question',
                'message' => 'A rate limit test message.',
                'submitted_at' => (string) (microtime(true) - 5),
            ])->assertRedirect(route('home').'#contact');
        }

        $this->from(route('home'))
            ->post(route('contact.store'), [
                'name' => 'Retry Sender',
                'email' => 'retry@example.test',
                'subject' => 'Other',
                'message' => 'Please preserve my message for a later retry.',
                'honeypot' => '',
                'submitted_at' => (string) (microtime(true) - 5),
            ])
            ->assertRedirect(route('home').'#contact')
            ->assertSessionHas('contact_status', 'Too many messages were sent recently. Please try again later.')
            ->assertSessionHas('_old_input.name', 'Retry Sender')
            ->assertSessionHas('_old_input.email', 'retry@example.test')
            ->assertSessionHas('_old_input.message', 'Please preserve my message for a later retry.')
            ->assertSessionMissing('_old_input.submitted_at')
            ->assertSessionMissing('_old_input.honeypot');
    }

    public function test_contact_invalid_no_javascript_submission_returns_to_the_contact_fragment(): void
    {
        $this->from(route('home'))
            ->post(route('contact.store'), [
                'name' => '',
                'email' => 'not-an-email',
                'subject' => '',
                'message' => '',
                'submitted_at' => (string) (microtime(true) - 5),
            ])
            ->assertRedirect(route('home').'#contact')
            ->assertSessionHasErrors(['name', 'email', 'subject', 'message'])
            ->assertSessionHas('_old_input');
    }

    public function test_reply_chooser_contains_encoded_client_options_without_the_message_body(): void
    {
        $manager = $this->userWithRole('manager');
        $message = ContactMessage::query()->create([
            'reference' => 'FG-REPLY-1234',
            'name' => 'Taylor Sender',
            'email' => 'taylor+reply@example.test',
            'subject' => 'Question & quote "please"',
            'message' => 'PRIVATE CUSTOMER MESSAGE BODY',
            'status' => ContactMessage::STATUS_READ,
            'read_at' => now(),
        ]);

        $response = $this->actingAs($manager)->get(route('admin.messages.show', $message))->assertOk();
        $response->assertSee('Default mail app')
            ->assertSee('Microsoft 365')
            ->assertSee('Yahoo Mail')
            ->assertSee('Remember my choice')
            ->assertSee('aria-haspopup="menu"', false)
            ->assertSee('rel="noopener noreferrer"', false)
            ->assertSee(rawurlencode('Question & quote "please"'), false)
            ->assertSee(rawurlencode('Hello,'.PHP_EOL.PHP_EOL.'Following up on reference FG-REPLY-1234.'), false)
            ->assertDontSee('PRIVATE%20CUSTOMER%20MESSAGE%20BODY');

        $this->assertSame(2, substr_count($response->getContent(), 'data-reply-chooser'));
    }

    public function test_map_and_social_parser_services_accept_known_values(): void
    {
        $this->assertTrue(app(MapLink::class)->parse('https://google.com/maps?q=Panabo')['valid']);
        $this->assertSame('fleetguard', app(SocialProfile::class)->parse('facebook', '@fleetguard')['username']);
    }

    public function test_manager_can_review_a_message_and_navigate_to_the_adjacent_inbox_record(): void
    {
        $manager = User::factory()->create();
        $manager->forceFill(['role' => 'manager'])->save();
        $olderMessage = ContactMessage::query()->create([
            'reference' => ContactMessage::newReference(),
            'name' => 'Older Inbox Sender',
            'email' => 'older@example.test',
            'subject' => 'General question',
            'message' => "First line.\nSecond line.",
            'status' => ContactMessage::STATUS_READ,
            'read_at' => now()->subDay(),
        ]);
        $olderMessage->forceFill([
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ])->save();
        $newerMessage = ContactMessage::query()->create([
            'reference' => ContactMessage::newReference(),
            'name' => 'Newer Inbox Sender',
            'email' => 'newer@example.test',
            'subject' => 'Booking question',
            'message' => 'A question about a booking.',
            'status' => ContactMessage::STATUS_READ,
            'read_at' => now(),
        ]);

        $this->actingAs($manager)
            ->get(route('admin.messages.index'))
            ->assertOk()
            ->assertSee('Messages inbox')
            ->assertSee('Newer Inbox Sender');

        $this->get(route('admin.messages.show', $olderMessage))
            ->assertOk()
            ->assertSee('Message timeline')
            ->assertSee('First line.')
            ->assertSee('Second line.')
            ->assertSee('Previous message')
            ->assertSee(route('admin.messages.show', $newerMessage));
    }

    public function test_message_navigation_keeps_all_controls_and_disables_missing_neighbors(): void
    {
        $admin = $this->userWithRole('admin');
        $messages = [];

        foreach ([
            ['Oldest sender', ContactMessage::STATUS_READ, 30],
            ['Middle sender with a very long name for truncation checks', ContactMessage::STATUS_REPLIED, 20],
            ['Newest sender', ContactMessage::STATUS_ARCHIVED, 10],
        ] as [$name, $status, $minutesAgo]) {
            $message = ContactMessage::query()->create([
                'reference' => ContactMessage::newReference(),
                'name' => $name,
                'email' => strtolower(str_replace(' ', '.', $name)).'@example.test',
                'subject' => 'General question',
                'message' => 'A navigation test message.',
                'status' => $status,
            ]);
            $message->forceFill([
                'created_at' => now()->subMinutes($minutesAgo),
                'updated_at' => now()->subMinutes($minutesAgo),
            ])->save();
            $messages[] = $message;
        }

        [$oldest, $middle, $newest] = $messages;

        $oldestResponse = $this->actingAs($admin)->get(route('admin.messages.show', $oldest))->assertOk();
        $oldestResponse->assertSee('aria-label="Previous message: '.$middle->name.', Replied"', false)
            ->assertSee('admin-message-detail-nav-status-dot--replied', false)
            ->assertSee('aria-label="No next message"', false)
            ->assertSee('class="admin-message-detail-nav-control admin-message-detail-nav-control--back" href="'.route('admin.messages.index').'"', false)
            ->assertSee('href="'.route('admin.messages.show', $middle).'"', false);

        $middleResponse = $this->get(route('admin.messages.show', $middle))->assertOk();
        $middleResponse->assertSee('aria-label="Previous message: '.$newest->name.', Archived"', false)
            ->assertSee('aria-label="Next message: '.$oldest->name.', Read"', false)
            ->assertSee('bi-inbox', false);

        $newestResponse = $this->get(route('admin.messages.show', $newest))->assertOk();
        $newestResponse->assertSee('aria-label="No previous message"', false)
            ->assertSee('aria-label="Next message: '.$middle->name.', Replied"', false)
            ->assertSee('href="'.route('admin.messages.show', $middle).'"', false);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();

        return $user->fresh();
    }

    private function completeSettingsPayload(): array
    {
        return [
            'name' => 'FleetGuard Mobility',
            'tagline' => 'Travel well, arrive ready.',
            'phone' => '+63 912 345 6789',
            'email' => 'hello@fleetguard.test',
            'address' => 'Panabo City, Philippines',
            'business_hours' => 'Monday to Saturday, 8 AM to 5 PM',
            'maps_url' => '<iframe width="600" src="https://www.google.com/maps/embed?pb=!1m0&amp;key=fleetguard" allowfullscreen></iframe>',
            'text_number' => '(0912) 345-6789',
            'social' => [
                'facebook' => '@fleetguard',
                'instagram' => 'https://www.instagram.com/fleet.guard/?utm_source=admin',
                'tiktok' => 'https://www.tiktok.com/@fleetguard?lang=en',
                'x' => 'https://twitter.com/fleet_guard?utm_source=admin',
                'youtube' => '@FleetGuard',
                'messenger' => 'm.me/FleetGuard?ref=admin',
            ],
        ];
    }
}
