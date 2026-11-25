@php
    $isPreview = $isPreview ?? false;
    $reviewId = $testimonial->exists ? 'testimonial-review-'.$testimonial->id : 'testimonial-preview-review';
    $reviewDate = $testimonial->review_date;
@endphp
<article @class(['testimonial-card', 'testimonial-card--featured' => ! $isPreview && $testimonial->is_featured]) @if (! $isPreview) data-scroll-reveal role="listitem" @else data-testimonial-preview-card @endif>
    <div class="testimonial-card__quote-mark" aria-hidden="true"><i class="bi bi-quote"></i></div>
    @if ($testimonial->rating)
        <div class="testimonial-card__rating" role="img" aria-label="Rated {{ $testimonial->rating }} out of 5" data-testimonial-rating>
            @for ($star = 1; $star <= 5; $star++)
                <i class="bi {{ $star <= $testimonial->rating ? 'bi-star-fill' : 'bi-star' }}" aria-hidden="true"></i>
            @endfor
        </div>
    @else
        <div class="testimonial-card__rating" role="img" aria-label="Rating preview" data-testimonial-rating hidden></div>
    @endif
    <p @class(['testimonial-card__review', 'is-truncated' => ! $isPreview]) id="{{ $reviewId }}" data-testimonial-review>{{ $testimonial->review_content }}</p>
    <button
        class="testimonial-card__read-more"
        type="button"
        @if ($isPreview)
            aria-expanded="false"
            aria-controls="{{ $reviewId }}"
        @else
            aria-haspopup="dialog"
            aria-controls="testimonial-review-dialog"
            data-testimonial-full-review="{{ $testimonial->review_content }}"
            data-testimonial-full-author="{{ $testimonial->customer_display_name ?: 'Author name' }}"
        @endif
        data-testimonial-read-more
        hidden
    >Read more</button>
    <footer class="testimonial-card__author">
        <span class="testimonial-card__avatar">
            @if ($testimonial->profile_image_path)
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($testimonial->profile_image_path) }}" alt="" loading="lazy" decoding="async" data-testimonial-avatar-image>
                <span data-testimonial-avatar-initials hidden>{{ $testimonial->initials() }}</span>
            @else
                <img src="" alt="" hidden data-testimonial-avatar-image>
                <span data-testimonial-avatar-initials>{{ $testimonial->initials() ?: '—' }}</span>
            @endif
        </span>
        <span class="testimonial-card__author-copy">
            <strong data-testimonial-author>{{ $testimonial->customer_display_name ?: 'Author name' }}</strong>
            <time datetime="{{ $reviewDate?->toDateString() }}" data-testimonial-date>{{ $reviewDate?->translatedFormat('M j, Y') ?? 'Review date' }}</time>
        </span>
        <span class="testimonial-card__source" data-testimonial-source>{{ $testimonial->source?->label() ?? 'Manual' }}</span>
    </footer>
</article>
