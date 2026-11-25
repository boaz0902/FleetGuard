<?php

namespace Database\Factories;

use App\Enums\TestimonialSource;
use App\Enums\TestimonialStatus;
use App\Models\Testimonial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Testimonial>
 */
class TestimonialFactory extends Factory
{
    protected $model = Testimonial::class;

    public function definition(): array
    {
        return [
            'customer_display_name' => fake()->name(),
            'review_content' => fake()->paragraph(),
            'rating' => fake()->numberBetween(1, 5),
            'review_date' => fake()->dateTimeBetween('-2 years', 'today')->format('Y-m-d'),
            'profile_image_path' => null,
            'source' => TestimonialSource::Manual,
            'status' => TestimonialStatus::Draft,
            'is_featured' => false,
            'display_order' => 0,
            'user_id' => null,
            'booking_id' => null,
        ];
    }
}
