<section class="public-content-section contact-section" id="contact" aria-labelledby="contact-title" data-reveal-section>
    <div class="public-content-shell">
        <header class="public-section-heading" data-scroll-reveal>
            <p class="fleet-eyebrow homepage-section-label"><span><strong>06</strong> / Contact</span></p>
            <h2 id="contact-title">Let's plan your next trip.</h2>
            <p>Have a question about a request? Reach out through the FleetGuard contact details below.</p>
        </header>

        <div class="contact-grid">
            <article class="contact-card contact-information-card" data-scroll-reveal>
                <span class="public-icon-tile"><i class="bi bi-chat-dots" aria-hidden="true"></i></span>
                <h3>Contact FleetGuard</h3>
                @include('partials.public-contact-details')
            </article>

            <article class="contact-card contact-form-card" data-scroll-reveal>
                <span class="public-icon-tile"><i class="bi bi-envelope-paper" aria-hidden="true"></i></span>
                <h3>Send a message</h3>

                @if (session('status'))
                    <div class="contact-form-status" aria-live="polite" style="margin-bottom: 1rem; padding: .75rem 1rem; border-radius: 0.75rem; background: rgba(16,185,129,.1); border: 1px solid rgba(16,185,129,.3); color: #0f172a;">
                        {{ session('status') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('contact.store') }}" novalidate>
                    @csrf
                    <input type="hidden" name="submitted_at" value="{{ microtime(true) }}">
                    <input type="text" name="honeypot" tabindex="-1" autocomplete="off" style="position:absolute; left:-9999px; width:1px; height:1px; opacity:0;" aria-hidden="true">

                    <div class="contact-form-grid">
                        <label>
                            <span>Name</span>
                            <input type="text" name="name" value="{{ old('name', auth()->user()?->name ?? '') }}" required>
                            @error('name')<small>{{ $message }}</small>@enderror
                        </label>
                        <label>
                            <span>Email</span>
                            <input type="email" name="email" value="{{ old('email', auth()->user()?->email ?? '') }}" required {{ auth()->check() ? 'readonly' : '' }}>
                            @error('email')<small>{{ $message }}</small>@enderror
                        </label>
                        <label>
                            <span>Phone</span>
                            <input type="tel" name="phone" value="{{ old('phone') }}">
                            @error('phone')<small>{{ $message }}</small>@enderror
                        </label>
                        <label>
                            <span>Subject</span>
                            <select name="subject" required>
                                <option value="">Select a topic</option>
                                @foreach (['General question', 'Booking question', 'Vehicle question', 'Feedback', 'Other'] as $option)
                                    <option value="{{ $option }}" @selected(old('subject') === $option)>{{ $option }}</option>
                                @endforeach
                            </select>
                            @error('subject')<small>{{ $message }}</small>@enderror
                        </label>
                        <label class="contact-message-field">
                            <span>Message</span>
                            <textarea name="message" rows="5" maxlength="2000" required>{{ old('message') }}</textarea>
                            <small class="contact-form-charcount">{{ strlen((string) old('message')) }}/2000</small>
                            @error('message')<small>{{ $message }}</small>@enderror
                        </label>
                    </div>

                    <button class="button button-primary contact-request-button" type="submit">
                        Send message <i class="bi bi-send" aria-hidden="true"></i>
                    </button>
                </form>
            </article>

            <article class="contact-card contact-location-card" data-scroll-reveal>
                <span class="public-icon-tile"><i class="bi bi-geo-alt" aria-hidden="true"></i></span>
                <p class="contact-overline">Based in</p>
                <h3>Panabo City</h3>
                <p>Philippines</p>
                <div class="contact-location-note">
                    <i class="bi bi-info-circle" aria-hidden="true"></i>
                    <span>Specific office or pickup details will be shared by FleetGuard when confirmed.</span>
                </div>
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
