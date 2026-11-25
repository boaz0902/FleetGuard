<?php

namespace App\Models;

use App\Enums\TestimonialSource;
use App\Enums\TestimonialStatus;
use Database\Factories\TestimonialFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Testimonial extends Model
{
    /** @use HasFactory<TestimonialFactory> */
    use HasFactory;

    protected $fillable = [
        'customer_display_name',
        'review_content',
        'rating',
        'review_date',
        'profile_image_path',
        'source',
        'status',
        'is_featured',
        'display_order',
        'user_id',
        'booking_id',
        'consent_given',
        'consented_at',
    ];

    protected function casts(): array
    {
        return [
            'source' => TestimonialSource::class,
            'status' => TestimonialStatus::class,
            'rating' => 'integer',
            'review_date' => 'date',
            'is_featured' => 'boolean',
            'display_order' => 'integer',
            'consent_given' => 'boolean',
            'consented_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', TestimonialStatus::Published->value);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderByDesc('is_featured')
            ->orderBy('display_order')
            ->orderByDesc('review_date')
            ->orderBy('id');
    }

    public function initials(): string
    {
        return collect(preg_split('/\s+/', trim($this->customer_display_name)) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }
}
