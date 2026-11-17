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
    $configuredMapUrl = config('fleetguard.maps_url');
    $mapLink = app(\App\Services\MapLink::class)->parse(is_string($configuredMapUrl) ? $configuredMapUrl : null, $contactAddress);
    $socialParser = app(\App\Services\SocialProfile::class);
    $textNumberParser = app(\App\Services\TextMessageNumber::class);
    $socialVariant = ($socialVariant ?? 'card') === 'footer' ? 'footer' : 'card';
    $socialProfiles = [];
    foreach (\App\Services\SocialProfile::platforms() as $platform => $definition) {
        $profileUrl = config('fleetguard.social.'.$platform);
        $profile = $socialParser->parse($platform, is_string($profileUrl) ? $profileUrl : null);
        if ($profile['valid'] && $profile['url']) {
            $socialProfiles[] = $profile;
        }
    }
    $textNumber = config('fleetguard.text_number');
    $textNumber = is_string($textNumber) && trim($textNumber) !== '' ? $textNumber : $contactPhone;
    $textProfile = $textNumberParser->parse($textNumber);
    if ($textProfile['valid'] && $textProfile['url']) {
        $socialProfiles[] = $textProfile;
    }
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
                <span class="public-business-hours-value">{{ $businessHours }}</span>
            @else
                <span class="public-placeholder"><i class="bi bi-info-circle" aria-hidden="true"></i> To be added</span>
            @endif
        </dd>
    </div>
    <div>
        <dt><i class="bi bi-map" aria-hidden="true"></i> Map</dt>
        <dd>
            @if ($mapLink['valid'] && $mapLink['open_url'])
                <a href="{{ $mapLink['open_url'] }}" target="_blank" rel="noopener noreferrer">Open in Maps</a>
            @else
                <span class="public-placeholder"><i class="bi bi-info-circle" aria-hidden="true"></i> To be added</span>
            @endif
        </dd>
    </div>
</dl>

@include('partials.social-links', ['profiles' => $socialProfiles, 'variant' => $socialVariant])
