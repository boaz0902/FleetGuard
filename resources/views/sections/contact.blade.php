@php
    $configuredMapUrl = config('fleetguard.maps_url');
    $contactAddress = config('fleetguard.address');
    $contactMap = app(\App\Services\MapLink::class)->parse(
        is_string($configuredMapUrl) ? $configuredMapUrl : null,
        is_string($contactAddress) ? $contactAddress : null,
    );
@endphp

<section class="public-content-section contact-section" id="contact" aria-labelledby="contact-title" data-reveal-section>
    <div class="public-content-shell">
        <header class="public-section-heading" data-scroll-reveal>
            <p class="fleet-eyebrow homepage-section-label"><span><strong>06</strong> / Contact</span></p>
            <h2 id="contact-title">Let's plan your next trip.</h2>
            <p>Have a question about a request? Reach out through the {{ config('fleetguard.name') ?: 'FleetGuard' }} contact details below.</p>
        </header>

        <div class="contact-grid">
            <article class="contact-card contact-information-card" data-scroll-reveal>
                <span class="public-icon-tile"><i class="bi bi-chat-dots" aria-hidden="true"></i></span>
                <h3>Contact {{ config('fleetguard.name') ?: 'FleetGuard' }}</h3>
                @include('partials.public-contact-details')
            </article>

            <article class="contact-card contact-form-card" data-scroll-reveal>
                <span class="public-icon-tile"><i class="bi bi-envelope-paper" aria-hidden="true"></i></span>
                <h3>Send a message</h3>

                @if (session('status') || session('contact_status'))
                    <div class="contact-form-status" role="status" aria-live="polite">
                        {{ session('status') ?? session('contact_status') }}
                    </div>
                @endif

                <div class="contact-form-feedback" data-contact-feedback role="status" aria-live="polite" hidden></div>

                <form method="POST" action="{{ route('contact.store') }}" novalidate data-contact-form>
                    @csrf
                    <input type="hidden" name="submitted_at" value="{{ microtime(true) }}" data-contact-submitted-at>
                    <input type="text" name="honeypot" tabindex="-1" autocomplete="off" style="position:absolute; left:-9999px; width:1px; height:1px; opacity:0;" aria-hidden="true">

                    <div class="contact-form-grid">
                        <label>
                            <span>Name</span>
                            <span class="contact-form-input-wrap">
                                <i class="bi bi-person" aria-hidden="true"></i>
                                <input type="text" name="name" value="{{ old('name', auth()->user()?->name ?? '') }}" autocomplete="name" required aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}" @error('name') aria-describedby="contact-name-error" @enderror>
                            </span>
                            @error('name')<small class="contact-field-error" id="contact-name-error">{{ $message }}</small>@enderror
                        </label>
                        <label>
                            <span>Email</span>
                            <span class="contact-form-input-wrap">
                                <i class="bi bi-envelope" aria-hidden="true"></i>
                                <input type="email" name="email" value="{{ old('email', auth()->user()?->email ?? '') }}" autocomplete="email" required aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}" @error('email') aria-describedby="contact-email-error" @enderror {{ auth()->check() ? 'readonly' : '' }}>
                            </span>
                            @error('email')<small class="contact-field-error" id="contact-email-error">{{ $message }}</small>@enderror
                        </label>
                        <label>
                            <span>Phone</span>
                            <span class="contact-form-input-wrap">
                                <i class="bi bi-telephone" aria-hidden="true"></i>
                                <input type="tel" name="phone" value="{{ old('phone') }}" autocomplete="tel" aria-invalid="{{ $errors->has('phone') ? 'true' : 'false' }}" @error('phone') aria-describedby="contact-phone-error" @enderror>
                            </span>
                            @error('phone')<small class="contact-field-error" id="contact-phone-error">{{ $message }}</small>@enderror
                        </label>
                        <label>
                            <span>Subject</span>
                            <span class="contact-form-input-wrap">
                                <i class="bi bi-chat-left-text" aria-hidden="true"></i>
                                <select name="subject" required aria-invalid="{{ $errors->has('subject') ? 'true' : 'false' }}" @error('subject') aria-describedby="contact-subject-error" @enderror>
                                    <option value="">Select a topic</option>
                                    @foreach (['General question', 'Booking question', 'Vehicle question', 'Feedback', 'Other'] as $option)
                                        <option value="{{ $option }}" @selected(old('subject') === $option)>{{ $option }}</option>
                                    @endforeach
                                </select>
                            </span>
                            @error('subject')<small class="contact-field-error" id="contact-subject-error">{{ $message }}</small>@enderror
                        </label>
                        <label class="contact-message-field">
                            <span>Message</span>
                            <span class="contact-form-input-wrap contact-form-input-wrap--textarea">
                                <i class="bi bi-pencil-square" aria-hidden="true"></i>
                                <textarea name="message" rows="5" maxlength="2000" required data-contact-message aria-invalid="{{ $errors->has('message') ? 'true' : 'false' }}" @error('message') aria-describedby="contact-message-error contact-message-counter" @else aria-describedby="contact-message-counter" @enderror>{{ old('message') }}</textarea>
                            </span>
                            <small class="contact-form-charcount" id="contact-message-counter" data-contact-counter aria-live="polite">{{ mb_strlen((string) old('message')) }}/2000 characters</small>
                            @error('message')<small class="contact-field-error" id="contact-message-error">{{ $message }}</small>@enderror
                        </label>
                    </div>

                    <button class="button button-primary contact-request-button" type="submit">
                        <span data-contact-submit-label aria-live="polite">Send message</span> <i class="bi bi-send" aria-hidden="true" data-contact-submit-icon></i>
                    </button>
                </form>
            </article>

            <article class="contact-card contact-location-card" data-scroll-reveal>
                <span class="public-icon-tile"><i class="bi bi-geo-alt" aria-hidden="true"></i></span>
                @if ($contactMap['valid'] && $contactMap['embed_url'])
                    <p class="contact-overline">Find us</p>
                    <h3>{{ is_string($contactAddress) && trim($contactAddress) !== '' ? $contactAddress : 'FleetGuard location' }}</h3>
                    <div class="contact-map-frame">
                        <iframe src="{{ $contactMap['embed_url'] }}" title="FleetGuard location map" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                    </div>
                    <a class="button button-primary contact-map-link" href="{{ $contactMap['open_url'] }}" target="_blank" rel="noopener noreferrer">
                        <i class="bi bi-geo-alt" aria-hidden="true"></i> Open in Maps
                    </a>
                @elseif ($contactMap['valid'] && $contactMap['open_url'])
                    <p class="contact-overline">Find us</p>
                    <h3>{{ is_string($contactAddress) && trim($contactAddress) !== '' ? $contactAddress : 'FleetGuard location' }}</h3>
                    <div class="contact-location-note">
                        <i class="bi bi-info-circle" aria-hidden="true"></i>
                        <span>This Google Maps short link can be opened in Maps but does not support an embedded preview.</span>
                    </div>
                    <a class="button button-primary contact-map-link" href="{{ $contactMap['open_url'] }}" target="_blank" rel="noopener noreferrer">
                        <i class="bi bi-geo-alt" aria-hidden="true"></i> Open in Maps
                    </a>
                @else
                    <p class="contact-overline">Based in</p>
                    <h3>Panabo City</h3>
                    <p>Philippines</p>
                    <div class="contact-location-note">
                        <i class="bi bi-info-circle" aria-hidden="true"></i>
                        <span>Map details: To be added. Specific office or pickup details will be shared by FleetGuard when confirmed.</span>
                    </div>
                @endif
            </article>

            <article class="contact-card contact-request-card" data-scroll-reveal>
                <span class="public-icon-tile"><i class="bi bi-car-front" aria-hidden="true"></i></span>
                <h3>Ready to make a request?</h3>
                <p>Explore the sample fleet and choose dates to begin a request. Availability and rates are confirmed by FleetGuard.</p>
                <a class="button button-primary contact-request-button" href="#fleet">
                    Explore the fleet <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
                </a>
            </article>
        </div>
    </div>
</section>
