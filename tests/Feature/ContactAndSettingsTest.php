<?php

namespace Tests\Feature;

use App\Mail\FleetGuardMessageMail;
use App\Models\ContactMessage;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
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

        $response->assertRedirect(route('home'));
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
}
