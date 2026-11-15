@php
    $customerHeroUser = auth()->user();
    $customerHeroInitials = collect(preg_split('/\s+/', trim($customerHeroUser->name)) ?: [])
        ->filter()
        ->take(2)
        ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');
@endphp

<header class="customer-bookings-hero">
    <div class="customer-bookings-hero-inner">
        <div class="customer-hero-copy">
            @if (! empty($heroBackUrl))
                <a class="customer-hero-backlink" href="{{ $heroBackUrl }}">
                    <i class="bi bi-arrow-left" aria-hidden="true"></i> {{ $heroBackText ?? 'Back' }}
                </a>
            @endif
            <p class="customer-hero-eyebrow">My account</p>
            <h1>{{ $heroTitle }}</h1>
            <p class="customer-hero-email">{{ $heroDescription }}</p>
        </div>
        <div class="customer-hero-profile">
            <span class="customer-avatar" aria-hidden="true">{{ $customerHeroInitials ?: 'FG' }}</span>
            @if ($customerHeroUser->hasVerifiedEmail())
                <span class="customer-hero-verified"><i class="bi bi-patch-check-fill" aria-hidden="true"></i> Verified</span>
            @else
                <span class="customer-hero-unverified"><i class="bi bi-envelope-exclamation" aria-hidden="true"></i> Verify email</span>
            @endif
            @isset($heroStatusMeta)
                @include('partials.customer-booking-status', ['statusMeta' => $heroStatusMeta])
            @endisset
        </div>
    </div>
</header>
