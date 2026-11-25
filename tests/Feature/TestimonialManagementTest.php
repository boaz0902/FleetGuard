<?php

namespace Tests\Feature;

use App\Enums\TestimonialSource;
use App\Enums\TestimonialStatus;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TestimonialManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_shows_an_empty_state_without_inventing_reviews(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('What Our Customers Say')
            ->assertSee('Real experiences. Honest feedback. Better journeys.')
            ->assertSee('testimonial-empty-state', false)
            ->assertSee('Customer stories will appear here when reviews are available.')
            ->assertDontSee('Rated 5 out of 5');
    }

    public function test_homepage_shows_only_published_testimonials_in_featured_and_display_order(): void
    {
        Testimonial::factory()->create([
            'customer_display_name' => 'Draft Reviewer',
            'review_content' => 'Draft review hidden from public.',
            'status' => TestimonialStatus::Draft,
        ]);
        Testimonial::factory()->create([
            'customer_display_name' => 'Pending Reviewer',
            'review_content' => 'Pending review hidden from public.',
            'status' => TestimonialStatus::Pending,
        ]);
        Testimonial::factory()->create([
            'customer_display_name' => 'Rejected Reviewer',
            'review_content' => 'Rejected review hidden from public.',
            'status' => TestimonialStatus::Rejected,
        ]);
        Testimonial::factory()->create([
            'customer_display_name' => 'Standard Later',
            'review_content' => 'Standard review ordered later.',
            'status' => TestimonialStatus::Published,
            'display_order' => 20,
        ]);
        Testimonial::factory()->create([
            'customer_display_name' => 'Standard Earlier',
            'review_content' => 'Standard review ordered earlier.',
            'status' => TestimonialStatus::Published,
            'display_order' => 2,
        ]);
        Testimonial::factory()->create([
            'customer_display_name' => 'Featured Reviewer',
            'review_content' => 'Featured review appears first.',
            'rating' => 5,
            'status' => TestimonialStatus::Published,
            'is_featured' => true,
            'display_order' => 80,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSeeInOrder([
                'Featured review appears first.',
                'Standard review ordered earlier.',
                'Standard review ordered later.',
            ])
            ->assertSee('Rated 5 out of 5', false)
            ->assertDontSee('Draft review hidden from public.')
            ->assertDontSee('Pending review hidden from public.')
            ->assertDontSee('Rejected review hidden from public.');
    }

    public function test_homepage_escapes_customer_names_and_review_content(): void
    {
        Testimonial::factory()->create([
            'customer_display_name' => '<img src=x onerror=alert(1)>',
            'review_content' => '<script>alert(1)</script>',
            'status' => TestimonialStatus::Published,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('&lt;img src=x onerror=alert(1)&gt;', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_admin_can_create_search_filter_update_publish_unpublish_feature_and_delete_manual_entries(): void
    {
        $admin = $this->userWithRole('admin');
        $this->actingAs($admin)
            ->get(route('admin.testimonials.index'))
            ->assertOk()
            ->assertSee('Customer Reviews')
            ->assertSee('No testimonials yet');

        $this->post(route('admin.testimonials.store'), $this->payload())
            ->assertRedirect();
        $testimonial = Testimonial::query()->firstOrFail();
        $this->assertSame(TestimonialSource::Manual, $testimonial->source);
        $this->assertSame(TestimonialStatus::Draft, $testimonial->status);
        $this->get(route('admin.testimonials.edit', $testimonial))
            ->assertOk()
            ->assertSee('data-testimonial-form', false)
            ->assertSee('data-testimonial-preview-card', false)
            ->assertSee('Customer Reviews', false);

        $this->post(route('admin.testimonials.publish', $testimonial))->assertRedirect();
        $this->post(route('admin.testimonials.featured', $testimonial))->assertRedirect();
        $testimonial->refresh();
        $this->assertSame(TestimonialStatus::Published, $testimonial->status);
        $this->assertTrue($testimonial->is_featured);

        $this->get(route('admin.testimonials.index', [
            'search' => 'Rina',
            'source' => 'manual',
            'status' => 'published',
            'rating' => 5,
            'featured' => 1,
        ]))
            ->assertOk()
            ->assertSee('Rina Sample')
            ->assertSee('An honest review for testing.')
            ->assertDontSee('No matching testimonials');

        $this->put(route('admin.testimonials.update', $testimonial), $this->payload([
            'customer_display_name' => 'Rina Updated',
            'review_content' => 'Updated and still authentic.',
            'rating' => 4,
            'is_featured' => 0,
            'display_order' => 3,
        ]))->assertRedirect();

        $testimonial->refresh();
        $this->assertSame('Rina Updated', $testimonial->customer_display_name);
        $this->assertSame(TestimonialSource::Manual, $testimonial->source);
        $this->assertSame(TestimonialStatus::Published, $testimonial->status);
        $this->assertFalse($testimonial->is_featured);

        $this->post(route('admin.testimonials.unpublish', $testimonial))->assertRedirect();
        $this->assertSame(TestimonialStatus::Draft, $testimonial->fresh()->status);
        $this->get(route('home'))->assertDontSee('Updated and still authentic.');

        $this->delete(route('admin.testimonials.destroy', $testimonial))
            ->assertRedirect(route('admin.testimonials.index'));
        $this->assertDatabaseMissing('testimonials', ['id' => $testimonial->id]);
    }

    public function test_manual_forms_cannot_set_google_source_or_a_managed_status(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->from(route('admin.testimonials.create'))
            ->post(route('admin.testimonials.store'), $this->payload([
                'source' => TestimonialSource::GoogleReview->value,
            ]))
            ->assertRedirect(route('admin.testimonials.create'))
            ->assertSessionHasErrors('source');

        $testimonial = Testimonial::factory()->create([
            'source' => TestimonialSource::Manual,
            'status' => TestimonialStatus::Draft,
        ]);

        $this->put(route('admin.testimonials.update', $testimonial), $this->payload([
            'source' => TestimonialSource::GoogleReview->value,
            'status' => TestimonialStatus::Published->value,
        ]))
            ->assertSessionHasErrors(['source', 'status']);

        $this->assertSame(TestimonialSource::Manual, $testimonial->fresh()->source);
        $this->assertSame(TestimonialStatus::Draft, $testimonial->fresh()->status);
        $this->assertDatabaseCount('testimonials', 1);
    }

    public function test_profile_image_upload_is_stored_safely_and_svg_is_rejected(): void
    {
        Storage::fake('public');
        $admin = $this->userWithRole('admin');
        $image = UploadedFile::fake()->createWithContent(
            'profile.jpg',
            file_get_contents(public_path('images/hero/fleetguard-hero-800.jpg')),
        );

        $this->actingAs($admin)
            ->post(route('admin.testimonials.store'), $this->payload(['profile_image' => $image]))
            ->assertRedirect();

        $testimonial = Testimonial::query()->firstOrFail();
        $this->assertStringStartsWith('testimonials/', $testimonial->profile_image_path);
        $imagePath = $testimonial->profile_image_path;
        Storage::disk('public')->assertExists($imagePath);

        $this->put(route('admin.testimonials.update', $testimonial), $this->payload([
            'remove_profile_image' => 1,
        ]))->assertRedirect();
        Storage::disk('public')->assertMissing($imagePath);
        $this->assertNull($testimonial->fresh()->profile_image_path);

        $svg = UploadedFile::fake()->createWithContent('profile.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
        $this->from(route('admin.testimonials.create'))
            ->post(route('admin.testimonials.store'), $this->payload(['profile_image' => $svg]))
            ->assertRedirect(route('admin.testimonials.create'))
            ->assertSessionHasErrors('profile_image');

        $this->assertDatabaseCount('testimonials', 1);
    }

    public function test_reorder_controls_swap_neighboring_items_without_changing_featured_priority(): void
    {
        $admin = $this->userWithRole('admin');
        $first = Testimonial::factory()->create(['display_order' => 0]);
        $second = Testimonial::factory()->create(['display_order' => 1]);
        $featured = Testimonial::factory()->create(['display_order' => 0, 'is_featured' => true]);

        $this->actingAs($admin)
            ->post(route('admin.testimonials.order', $second), ['direction' => 'up'])
            ->assertRedirect();

        $this->assertSame(1, $first->fresh()->display_order);
        $this->assertSame(0, $second->fresh()->display_order);
        $this->assertTrue($featured->fresh()->is_featured);
    }

    public function test_guests_customers_managers_and_staff_cannot_manage_testimonials(): void
    {
        $testimonial = Testimonial::factory()->create();

        $this->get(route('admin.testimonials.index'))
            ->assertRedirect(route('admin.login'));

        foreach (['customer', 'manager', 'staff'] as $role) {
            $user = $this->userWithRole($role);
            $this->actingAs($user)
                ->get(route('admin.testimonials.index'))
                ->assertForbidden();
            $this->post(route('admin.testimonials.publish', $testimonial))
                ->assertForbidden();
            $this->delete(route('admin.testimonials.destroy', $testimonial))
                ->assertForbidden();
        }

        $this->assertDatabaseHas('testimonials', ['id' => $testimonial->id]);
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'customer_display_name' => 'Rina Sample',
            'review_content' => 'An honest review for testing.',
            'rating' => 5,
            'review_date' => today()->subDay()->format('Y-m-d'),
            'display_order' => 0,
            'is_featured' => 0,
        ], $overrides);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();

        return $user;
    }
}
