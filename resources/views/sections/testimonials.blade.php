<section class="testimonials-section public-content-section" id="testimonials" aria-labelledby="testimonials-title" data-reveal-section>
    <div class="public-content-shell">
        <header class="public-section-heading" data-scroll-reveal>
            <p class="fleet-eyebrow homepage-section-label"><span>Customer stories</span></p>
            <h2 id="testimonials-title">What Our Customers Say</h2>
            <p>Real experiences. Honest feedback. Better journeys.</p>
        </header>

        @php
            $googleBusinessName = $googleReviews['business_name'] ?: config('fleetguard.name', 'FleetGuard');
            $showGooglePanel = $googleReviews['read_url'] || $googleReviews['write_url'];
            $testimonials = $testimonials
                ->sortByDesc('review_date')
                ->sortByDesc('is_featured')
                ->take(9)
                ->values();
        @endphp
        @if ($showGooglePanel)
            <aside class="google-reviews-panel" aria-label="Google review links" data-scroll-reveal>
                <div class="google-reviews-panel__copy">
                    <p class="google-reviews-panel__eyebrow"><i class="bi bi-google" aria-hidden="true"></i> Google Maps</p>
                    <h3>{{ $googleBusinessName }}</h3>
                    @if ($googleReviews['rating'] !== null || $googleReviews['review_count'] !== null)
                        <p class="google-reviews-panel__figures">
                            @if ($googleReviews['rating'] !== null)
                                <span><strong aria-label="Business-entered rating {{ number_format((float) $googleReviews['rating'], 1) }} out of 5">{{ number_format((float) $googleReviews['rating'], 1) }}</strong> / 5 rating</span>
                            @endif
                            @if ($googleReviews['review_count'] !== null)
                                <span><strong>{{ number_format((int) $googleReviews['review_count']) }}</strong> reviews</span>
                            @endif
                        </p>
                        <small class="google-reviews-panel__disclosure">Figures entered by the business; not live Google data.</small>
                    @else
                        <p class="google-reviews-panel__attribution">Visit our Google Maps profile to see customer reviews.</p>
                    @endif
                </div>
                <div class="google-reviews-panel__actions">
                    @if ($googleReviews['read_url'])
                        <a class="google-reviews-panel__link google-reviews-panel__link--primary" href="{{ $googleReviews['read_url'] }}" target="_blank" rel="noopener noreferrer">
                            Read All Google Reviews <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
                        </a>
                    @endif
                    @if ($googleReviews['write_url'])
                        <a class="google-reviews-panel__link" href="{{ $googleReviews['write_url'] }}" target="_blank" rel="noopener noreferrer">
                            Write a Google Review <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
                        </a>
                    @endif
                </div>
            </aside>
        @endif

        @if ($testimonials->isEmpty())
            <div class="testimonial-empty-state" role="status" data-scroll-reveal>
                <i class="bi bi-chat-square-quote" aria-hidden="true"></i>
                <p>Customer stories will appear here when reviews are available.</p>
            </div>
        @else
            <div class="testimonial-carousel" data-testimonial-carousel>
                <div class="testimonial-carousel-toolbar">
                    <div class="testimonial-carousel-progress" role="progressbar" aria-label="Testimonial carousel position" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" data-testimonial-progress>
                        <span></span>
                    </div>
                    <div class="testimonial-carousel-controls" aria-label="Testimonial navigation">
                        <button type="button" aria-label="Scroll to previous testimonials" aria-controls="testimonial-list" data-testimonial-prev disabled>
                            <i class="bi bi-arrow-left" aria-hidden="true"></i>
                        </button>
                        <button type="button" aria-label="Scroll to next testimonials" aria-controls="testimonial-list" data-testimonial-next>
                            <i class="bi bi-arrow-right" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
                <div class="testimonial-grid" id="testimonial-list" role="list" aria-label="Customer testimonials" data-testimonial-track tabindex="0">
                    @foreach ($testimonials as $testimonial)
                        @include('components.testimonial-card', ['testimonial' => $testimonial])
                    @endforeach
                </div>
            </div>
            <dialog class="testimonial-review-dialog" id="testimonial-review-dialog" aria-labelledby="testimonial-dialog-author" data-testimonial-dialog>
                <form method="dialog" class="testimonial-review-dialog__close-form">
                    <button class="testimonial-review-dialog__close" type="submit" aria-label="Close full review">
                        <i class="bi bi-x-lg" aria-hidden="true"></i>
                    </button>
                </form>
                <p class="testimonial-review-dialog__eyebrow">Customer story</p>
                <h3 id="testimonial-dialog-author" data-testimonial-dialog-author></h3>
                <p class="testimonial-review-dialog__content" data-testimonial-dialog-content></p>
            </dialog>
        @endif
    </div>
</section>
