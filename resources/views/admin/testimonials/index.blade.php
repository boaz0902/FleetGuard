@extends('layouts.admin')

@section('title', 'Customer Reviews | FleetGuard')
@section('admin-page-title', 'Customer Reviews')
@section('admin-page-icon', 'chat-quote')
@section('admin-breadcrumb', 'Management')
@section('admin-page-description', 'Manage customer testimonials displayed on the public homepage.')

@section('admin-content')
    <main class="admin-testimonials" id="main-content">
        @if (session('status'))
            <div class="admin-bookings-flash" role="status"><i class="bi bi-check-circle" aria-hidden="true"></i><span>{{ session('status') }}</span></div>
        @endif
        @if ($errors->any())
            <div class="admin-bookings-validation" role="alert"><i class="bi bi-exclamation-circle" aria-hidden="true"></i><span>{{ $errors->first() }}</span></div>
        @endif

        <section class="admin-page-intro">
            <div>
                <p class="admin-eyebrow">Social proof</p>
                <h2>Reviews &amp; testimonials</h2>
                <p>Only published testimonials appear on the homepage. New manual entries start as drafts.</p>
            </div>
            <a class="admin-primary-link" href="{{ route('admin.testimonials.create') }}"><i class="bi bi-plus-lg" aria-hidden="true"></i> Add testimonial</a>
        </section>

        <section class="admin-testimonials-panel admin-section-card" aria-labelledby="testimonial-list-heading">
            <div class="admin-testimonials-panel-heading">
                <div><p class="admin-eyebrow">Testimonial register</p><h2 id="testimonial-list-heading">All testimonials</h2></div>
                <span class="admin-testimonials-result-count">{{ number_format($testimonials->total()) }} results</span>
            </div>
            @if ($pendingCustomerSubmissions > 0)
                <a class="admin-testimonials-pending-link" href="{{ route('admin.testimonials.index', ['source' => \App\Enums\TestimonialSource::VerifiedCustomer->value, 'status' => \App\Enums\TestimonialStatus::Pending->value]) }}">
                    Review pending customer submissions ({{ $pendingCustomerSubmissions }})
                </a>
            @endif

            <form class="admin-testimonials-filters" method="GET" action="{{ route('admin.testimonials.index') }}">
                <label class="admin-testimonials-search">
                    <span>Search testimonials</span>
                    <input type="search" name="search" value="{{ request('search') }}" maxlength="100" placeholder="Customer name or review text">
                </label>
                <label><span>Source</span>
                    <select name="source">
                        <option value="">All sources</option>
                        @foreach ($sources as $source)
                            <option value="{{ $source->value }}" @selected(request('source') === $source->value)>{{ $source->label() }}</option>
                        @endforeach
                    </select>
                </label>
                <label><span>Status</span>
                    <select name="status">
                        <option value="">All statuses</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </label>
                <label><span>Rating</span>
                    <select name="rating">
                        <option value="">All ratings</option>
                        @for ($rating = 5; $rating >= 1; $rating--)
                            <option value="{{ $rating }}" @selected((string) request('rating') === (string) $rating)>{{ $rating }} out of 5</option>
                        @endfor
                    </select>
                </label>
                <label><span>Featured</span>
                    <select name="featured">
                        <option value="">All</option>
                        <option value="1" @selected(request('featured') === '1')>Featured</option>
                        <option value="0" @selected(request('featured') === '0')>Not featured</option>
                    </select>
                </label>
                <div class="admin-testimonials-filter-actions">
                    <button class="admin-testimonials-button" type="submit"><i class="bi bi-funnel" aria-hidden="true"></i> Filter</button>
                    @if (request()->query())
                        <a class="admin-testimonials-reset" href="{{ route('admin.testimonials.index') }}">Clear</a>
                    @endif
                </div>
            </form>

            <div class="admin-table-wrap">
                <table class="admin-testimonials-table">
                    <thead>
                        <tr>
                            <th scope="col">Customer &amp; review</th>
                            <th scope="col">Source</th>
                            <th scope="col">Status</th>
                            <th scope="col">Rating</th>
                            <th scope="col">Featured</th>
                            <th scope="col">Review date</th>
                            <th scope="col">Order</th>
                            <th scope="col"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($testimonials as $testimonial)
                            <tr>
                                <td data-label="Customer & review">
                                    <strong>{{ $testimonial->customer_display_name }}</strong>
                                    <span class="admin-testimonials-review-snippet">{{ \Illuminate\Support\Str::limit($testimonial->review_content, 120) }}</span>
                                    @if ($testimonial->source === \App\Enums\TestimonialSource::VerifiedCustomer)
                                        <span class="admin-testimonials-review-context">Customer: {{ $testimonial->booking?->customer?->full_name ?? $testimonial->user?->name ?? 'Customer account unavailable' }}</span>
                                        @if ($testimonial->booking)
                                            <a class="admin-testimonials-booking-link" href="{{ route('admin.bookings.show', $testimonial->booking) }}">Booking {{ $testimonial->booking->booking_reference }}</a>
                                        @endif
                                        <span class="admin-testimonials-review-context">
                                            Consent: {{ $testimonial->consent_given && $testimonial->consented_at ? 'Recorded' : 'Missing' }}
                                            @if ($testimonial->consented_at)
                                                · {{ $testimonial->consented_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}
                                            @endif
                                        </span>
                                    @endif
                                </td>
                                <td data-label="Source"><span class="admin-testimonials-source">{{ $testimonial->source->label() }}</span></td>
                                <td data-label="Status"><span class="admin-testimonials-status is-{{ $testimonial->status->value }}">{{ $testimonial->status->label() }}</span></td>
                                <td data-label="Rating"><span class="admin-testimonials-rating"><i class="bi bi-star-fill" aria-hidden="true"></i>{{ $testimonial->rating }}/5</span></td>
                                <td data-label="Featured">
                                    <span class="admin-testimonials-featured {{ $testimonial->is_featured ? 'is-featured' : '' }}">
                                        <i class="bi {{ $testimonial->is_featured ? 'bi-bookmark-star-fill' : 'bi-bookmark' }}" aria-hidden="true"></i>
                                        {{ $testimonial->is_featured ? 'Featured' : 'Standard' }}
                                    </span>
                                </td>
                                <td data-label="Review date">{{ $testimonial->review_date->timezone('Asia/Manila')->format('M j, Y') }}</td>
                                <td data-label="Order">{{ $testimonial->display_order }}</td>
                                <td data-label="Actions">
                                    <div class="admin-testimonials-row-actions">
                                        <a class="admin-testimonials-action-link" href="{{ route('admin.testimonials.edit', $testimonial) }}">Edit</a>
                                        <form method="POST" action="{{ route($testimonial->status === \App\Enums\TestimonialStatus::Published ? 'admin.testimonials.unpublish' : 'admin.testimonials.publish', $testimonial) }}">
                                            @csrf
                                            @php
                                                $needsConsent = $testimonial->source === \App\Enums\TestimonialSource::VerifiedCustomer && (! $testimonial->consent_given || ! $testimonial->consented_at);
                                            @endphp
                                            <button type="submit" @disabled($needsConsent) @if ($needsConsent) title="Recorded consent is required before publication." @endif>
                                                {{ $testimonial->status === \App\Enums\TestimonialStatus::Published ? 'Unpublish' : ($testimonial->source === \App\Enums\TestimonialSource::VerifiedCustomer ? 'Approve & publish' : 'Publish') }}
                                            </button>
                                        </form>
                                        @if ($testimonial->source === \App\Enums\TestimonialSource::VerifiedCustomer && $testimonial->status === \App\Enums\TestimonialStatus::Pending)
                                            <form method="POST" action="{{ route('admin.testimonials.reject', $testimonial) }}">
                                                @csrf
                                                <button class="is-danger" type="submit">Reject</button>
                                            </form>
                                        @endif
                                        <form method="POST" action="{{ route('admin.testimonials.featured', $testimonial) }}">
                                            @csrf
                                            <button type="submit">{{ $testimonial->is_featured ? 'Unfeature' : 'Feature' }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.testimonials.order', $testimonial) }}">
                                            @csrf
                                            <input type="hidden" name="direction" value="up">
                                            <button type="submit" aria-label="Move {{ $testimonial->customer_display_name }} up"><i class="bi bi-arrow-up" aria-hidden="true"></i></button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.testimonials.order', $testimonial) }}">
                                            @csrf
                                            <input type="hidden" name="direction" value="down">
                                            <button type="submit" aria-label="Move {{ $testimonial->customer_display_name }} down"><i class="bi bi-arrow-down" aria-hidden="true"></i></button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.testimonials.destroy', $testimonial) }}" onsubmit="return confirm('Delete this testimonial? This cannot be undone.')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="is-danger" type="submit">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="admin-table-empty" colspan="8">
                                    <strong>{{ request()->query() ? 'No matching testimonials' : 'No testimonials yet' }}</strong>
                                    <span>{{ request()->query() ? 'Try adjusting or clearing the filters.' : 'Add a real customer review when you have one ready to publish.' }}</span>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($testimonials->hasPages())
                <div class="admin-testimonials-pagination">{{ $testimonials->links() }}</div>
            @endif
        </section>
    </main>
@endsection
