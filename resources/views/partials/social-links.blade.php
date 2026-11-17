@php
    $profiles = collect($profiles ?? [])
        ->filter(fn ($profile) => is_array($profile) && ($profile['valid'] ?? false) && ! empty($profile['url']))
        ->values();
    $variant = ($variant ?? 'card') === 'footer' ? 'footer' : 'card';
    $includeTemplates = $includeTemplates ?? false;
    $hasDesktopProfile = $profiles->contains(fn ($profile) => empty($profile['touch_only']));
    $hasTouchProfile = $profiles->contains(fn ($profile) => ! empty($profile['touch_only']));
@endphp

@if ($hasDesktopProfile || $hasTouchProfile)
    <nav class="public-social-links public-social-links--{{ $variant }}{{ $hasDesktopProfile ? '' : ' public-social-links--touch-only-only' }}" aria-label="FleetGuard social media and text messaging">
        @foreach ($profiles as $profile)
            <a class="public-social-link{{ ! empty($profile['touch_only']) ? ' public-social-link--touch-only' : '' }}" href="{{ $profile['url'] }}" @if (str_starts_with($profile['url'], 'https://')) target="_blank" rel="noopener noreferrer" @endif aria-label="{{ $profile['aria_label'] ?? ('FleetGuard on '.$profile['name'].($profile['username'] ? ', @'.$profile['username'] : '')) }}" title="{{ $profile['name'] }}">
                <i class="public-social-link-icon bi bi-{{ $profile['icon'] }}" aria-hidden="true"></i>
                <span class="public-social-link-label">{{ $profile['label_style'] === 'handle' && $profile['username'] ? '@'.$profile['username'] : $profile['label'] }}</span>
            </a>
        @endforeach
    </nav>
@endif

@if ($includeTemplates)
    <template data-social-links-template>
        <nav class="public-social-links public-social-links--{{ $variant }}" aria-label="FleetGuard social media and text messaging"></nav>
    </template>
    <template data-social-link-template>
        <a class="public-social-link" href="#">
            <i class="public-social-link-icon bi bi-link-45deg" aria-hidden="true"></i>
            <span class="public-social-link-label"></span>
        </a>
    </template>
@endif
