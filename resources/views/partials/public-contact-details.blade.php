@php
    $contactPhone = config('fleetguard.phone');
    $contactPhone = is_string($contactPhone) && trim($contactPhone) !== '' ? trim($contactPhone) : null;
    $contactPhoneLink = $contactPhone ? preg_replace('/[^0-9+().,;*#]/', '', $contactPhone) : null;
    $contactEmail = config('fleetguard.email');
    $contactEmail = is_string($contactEmail) && filter_var($contactEmail, FILTER_VALIDATE_EMAIL) ? $contactEmail : null;
    $contactAddress = config('fleetguard.address');
    $contactAddress = is_string($contactAddress) && trim($contactAddress) !== '' ? trim($contactAddress) : null;
    $businessHours = config('fleetguard.business_hours');
    $businessHours = is_string($businessHours) && trim($businessHours) !== '' ? trim($businessHours) : null;
    $mapsUrl = config('fleetguard.maps_url');
    $mapsScheme = is_string($mapsUrl) ? parse_url($mapsUrl, PHP_URL_SCHEME) : null;
    $mapsUrl = in_array($mapsScheme, ['http', 'https'], true) ? $mapsUrl : null;
    $socialProfiles = [
        ['name' => 'Facebook', 'icon' => 'facebook', 'url' => config('fleetguard.social.facebook')],
        ['name' => 'Instagram', 'icon' => 'instagram', 'url' => config('fleetguard.social.instagram')],
        ['name' => 'TikTok', 'icon' => 'tiktok', 'url' => config('fleetguard.social.tiktok')],
    ];
    $socialProfiles = array_filter($socialProfiles, function ($profile) {
        $scheme = is_string($profile['url']) ? parse_url($profile['url'], PHP_URL_SCHEME) : null;

        return in_array($scheme, ['http', 'https'], true);
    });
@endphp

<dl class="public-contact-details">
    <div>
        <dt><i class="bi bi-telephone" aria-hidden="true"></i> Phone</dt>
        <dd>
            @if ($contactPhone)
                <a href="tel:{{ $contactPhoneLink }}">{{ $contactPhone }}</a>
            @else
                <span class="public-placeholder"><i class="bi bi-info-circle" aria-hidden="true"></i> To be added</span>
            @endif
        </dd>
    </div>
    <div>
        <dt><i class="bi bi-envelope" aria-hidden="true"></i> Email</dt>
        <dd>
            @if ($contactEmail)
                <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>
            @else
                <span class="public-placeholder"><i class="bi bi-info-circle" aria-hidden="true"></i> To be added</span>
            @endif
        </dd>
    </div>
    <div>
        <dt><i class="bi bi-geo-alt" aria-hidden="true"></i> Address</dt>
        <dd>
            @if ($contactAddress)
                {{ $contactAddress }}
            @else
                <span class="public-placeholder"><i class="bi bi-info-circle" aria-hidden="true"></i> To be added</span>
            @endif
        </dd>
    </div>
    <div>
        <dt><i class="bi bi-clock" aria-hidden="true"></i> Business hours</dt>
        <dd class="public-business-hours">
            @if ($businessHours)
                {{ $businessHours }}
            @else
                <span class="public-placeholder"><i class="bi bi-info-circle" aria-hidden="true"></i> To be added</span>
            @endif
        </dd>
    </div>
    <div>
        <dt><i class="bi bi-map" aria-hidden="true"></i> Map</dt>
        <dd>
            @if ($mapsUrl && $contactAddress)
                <a href="{{ $mapsUrl }}" target="_blank" rel="noopener noreferrer">Open in Maps</a>
            @else
                <span class="public-placeholder"><i class="bi bi-info-circle" aria-hidden="true"></i> To be added</span>
            @endif
        </dd>
    </div>
</dl>

@if ($socialProfiles)
    <div class="public-social-links" aria-label="FleetGuard social media">
        @foreach ($socialProfiles as $profile)
            <a href="{{ $profile['url'] }}" target="_blank" rel="noopener noreferrer" aria-label="FleetGuard on {{ $profile['name'] }}" title="{{ $profile['name'] }}">
                <i class="bi bi-{{ $profile['icon'] }}" aria-hidden="true"></i>
            </a>
        @endforeach
    </div>
@else
    <p class="public-social-placeholder"><span>Social links</span> <span class="public-placeholder"><i class="bi bi-info-circle" aria-hidden="true"></i> To be added</span></p>
@endif
