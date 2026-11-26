@extends('layouts.app')

@section('title', 'Legal & Policies | FleetGuard')
@section('meta-description', 'Review published FleetGuard policies and the versions you accepted with your bookings.')

@section('content')
    @include('partials.public-navigation')

    @php($contentRenderer = app(\App\Services\LegalPolicyContent::class))

    <main class="customer-dashboard-page customer-bookings-page" id="main-content">
        @include('partials.customer-account-hero', [
            'heroTitle' => 'Legal & Policies',
            'heroDescription' => 'Published documents and the policy versions recorded with your booking requests.',
        ])

        <div class="customer-dashboard-content legal-account-content">
            <section class="legal-account-card" aria-labelledby="published-policies-heading">
                <div class="legal-account-heading">
                    <div><p class="legal-eyebrow"><span aria-hidden="true"></span> Current documents</p><h2 id="published-policies-heading">Published policies</h2></div>
                    <a href="{{ route('home') }}#booking">Start a booking request <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
                </div>
                <nav class="legal-account-policy-list" aria-label="Published legal documents">
                    @forelse ($policies as $policy)
                        <a href="{{ route('legal.show', $policy->slug) }}"><span>{{ $policy->title }}</span><i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
                    @empty
                        <p class="legal-history-empty">No policies are currently published.</p>
                    @endforelse
                </nav>
            </section>

            <section class="legal-account-card" aria-labelledby="acceptance-history-heading">
                <div class="legal-account-heading"><div><p class="legal-eyebrow"><span aria-hidden="true"></span> Your account</p><h2 id="acceptance-history-heading">Acceptance history</h2></div></div>
                @if ($acceptances->isEmpty())
                    <div class="legal-history-empty">
                        <i class="bi bi-file-earmark-check" aria-hidden="true"></i>
                        <h3>No policy acceptances yet</h3>
                        <p>When you submit a booking request, the policy versions you accepted will appear here.</p>
                    </div>
                @else
                    <div class="legal-acceptance-list">
                        @foreach ($acceptances as $acceptance)
                            <details class="legal-acceptance-item">
                                <summary>
                                    <span><strong>{{ $acceptance->policy->title }}</strong><small>Version {{ $acceptance->version->version }} · Booking {{ $acceptance->booking->booking_reference ?? 'record unavailable' }}</small></span>
                                    <span class="legal-acceptance-date">{{ $acceptance->accepted_at->timezone('Asia/Manila')->format('M j, Y · g:i A') }}<small>{{ ucfirst($acceptance->status) }}</small></span>
                                </summary>
                                <div class="legal-accepted-content">
                                    <p>Accepted {{ $acceptance->accepted_at->timezone('Asia/Manila')->format('F j, Y \a\t g:i A') }} (Philippine time). Recorded version: {{ $acceptance->version->version }}.</p>
                                    <a href="{{ route('legal.show', $acceptance->policy->slug) }}">View current {{ $acceptance->policy->title }} <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
                                    {!! $contentRenderer->renderDocument($acceptance->version->content) !!}
                                </div>
                            </details>
                        @endforeach
                    </div>
                    @if (method_exists($acceptances, 'hasPages') && $acceptances->hasPages())
                        <div class="customer-booking-pagination">{{ $acceptances->links() }}</div>
                    @endif
                @endif
            </section>
        </div>
    </main>
@endsection
