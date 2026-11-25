@php($bookingCustomer = auth()->user()?->isCustomer() ? auth()->user() : null)
@php($agreementSlugs = ['terms-and-conditions', 'rental-policy', 'cancellation-policy', 'refund-policy'])
<section class="booking-experience" id="booking" aria-labelledby="booking-experience-title" data-booking-experience data-booking-url="{{ route('bookings.store') }}" data-availability-url="{{ route('vehicles.availability') }}" data-register-url="{{ route('register') }}" data-customer-account-state="{{ $bookingCustomer ? ($bookingCustomer->hasVerifiedEmail() ? 'verified' : 'unverified') : 'guest' }}">
    <div class="booking-experience-shell">
        <div class="booking-experience-intro">
            <p class="fleet-eyebrow homepage-section-label"><span><strong>03</strong> / Booking request</span></p>
            <div class="booking-experience-heading">
                <div>
                    <h2 id="booking-experience-title">Plan your next<br>great drive.</h2>
                    <p>Four steps to send your request for admin review. Requests are not confirmed automatically.</p>
                </div>
                <span class="booking-prototype-tag">Request for approval</span>
            </div>
        </div>

        <p class="booking-form-error" role="alert" data-booking-submit-error></p>

        <nav class="booking-stepper" aria-label="Booking progress" data-booking-stepper>
            <ol>
                <li class="is-current" data-step-indicator="0" aria-current="step"><span>01</span><span>Vehicle &amp; dates</span></li>
                <li data-step-indicator="1"><span>02</span><span>Trip details</span></li>
                <li data-step-indicator="2"><span>03</span><span>Your details</span></li>
                <li data-step-indicator="3"><span>04</span><span>Review</span></li>
            </ol>
            <div class="booking-stepper-track" aria-hidden="true"><span data-booking-progress></span></div>
        </nav>

        <div class="booking-step-panel is-active" data-booking-step="0" aria-labelledby="booking-vehicle-heading">
            <div class="booking-step-heading">
                <div>
                    <p class="booking-step-kicker">Step 01 <span>/ 04</span></p>
                    <h3 id="booking-vehicle-heading">Choose a vehicle and dates.</h3>
                    <p>Availability and rates come from FleetGuard's current vehicle records.</p>
                    <p class="booking-search-preference" data-search-preference hidden></p>
                </div>
                <a class="booking-text-link" href="#fleet">Explore the collection <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
            </div>

            <div class="booking-fields-grid booking-availability-fields">
                <div class="booking-field">
                    <label for="trip-pickup-date">Pickup date <span>*</span></label>
                    <input id="trip-pickup-date" name="pickup_date" type="date" required data-trip-pickup-date>
                </div>
                <div class="booking-field">
                    <label for="trip-pickup-time">Pickup time <span>*</span></label>
                    <select id="trip-pickup-time" name="pickup_time" required>
                        <option value="">Select time</option>
                        <option value="08:00">8:00 AM</option><option value="09:00">9:00 AM</option><option value="10:00">10:00 AM</option>
                        <option value="11:00">11:00 AM</option><option value="12:00">12:00 PM</option><option value="13:00">1:00 PM</option>
                        <option value="14:00">2:00 PM</option><option value="15:00">3:00 PM</option><option value="16:00">4:00 PM</option>
                        <option value="17:00">5:00 PM</option><option value="18:00">6:00 PM</option>
                    </select>
                </div>
                <div class="booking-field">
                    <label for="trip-return-date">Return date <span>*</span></label>
                    <input id="trip-return-date" name="return_date" type="date" required data-trip-return-date>
                </div>
                <div class="booking-field">
                    <label for="trip-return-time">Return time <span>*</span></label>
                    <select id="trip-return-time" name="return_time" required>
                        <option value="">Select time</option>
                        <option value="08:00">8:00 AM</option><option value="09:00">9:00 AM</option><option value="10:00">10:00 AM</option>
                        <option value="11:00">11:00 AM</option><option value="12:00">12:00 PM</option><option value="13:00">1:00 PM</option>
                        <option value="14:00">2:00 PM</option><option value="15:00">3:00 PM</option><option value="16:00">4:00 PM</option>
                        <option value="17:00">5:00 PM</option><option value="18:00">6:00 PM</option>
                    </select>
                </div>
            </div>
            <p class="booking-availability-status" role="status" aria-live="polite" data-availability-status>
                Choose pickup and return dates and times to check live availability.
            </p>

            <div class="booking-vehicle-grid" role="group" aria-label="Available vehicles" data-available-vehicles></div>

            <div class="booking-step-actions">
                <p class="booking-inline-note">Availability may change; the server checks again when you submit.</p>
                <button class="booking-action-button is-primary" type="button" data-booking-next disabled>
                    Continue to trip details <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
                </button>
            </div>
        </div>

        <div class="booking-step-panel" data-booking-step="1" aria-labelledby="booking-trip-heading" hidden inert>
            <div class="booking-step-heading">
                <div>
                    <p class="booking-step-kicker">Step 02 <span>/ 04</span></p>
                    <h3 id="booking-trip-heading">Choose your locations.</h3>
                    <p>Choose your pickup and return locations. Options below are demo locations in Panabo City.</p>
                </div>
                <button class="booking-text-link" type="button" data-booking-edit="0">Change vehicle <i class="bi bi-arrow-up-right" aria-hidden="true"></i></button>
            </div>

            <form class="booking-form-step" data-booking-trip-form novalidate>
                <div class="booking-fields-grid">
                    <div class="booking-field">
                        <label for="trip-pickup-location">Pickup location <span>*</span></label>
                        <select id="trip-pickup-location" name="pickup_location" required>
                            <option value="">Choose a demo location</option>
                            <option value="Panabo City Center (demo)">Panabo City Center (demo)</option>
                            <option value="Panabo Public Market (demo)">Panabo Public Market (demo)</option>
                            <option value="Panabo Transport Terminal (demo)">Panabo Transport Terminal (demo)</option>
                            <option value="Other location in Panabo (demo)">Other location in Panabo (demo)</option>
                        </select>
                    </div>
                    <div class="booking-field">
                        <label for="trip-return-location">Return location <span>*</span></label>
                        <select id="trip-return-location" name="return_location" required>
                            <option value="">Choose a demo location</option>
                            <option value="Panabo City Center (demo)">Panabo City Center (demo)</option>
                            <option value="Panabo Public Market (demo)">Panabo Public Market (demo)</option>
                            <option value="Panabo Transport Terminal (demo)">Panabo Transport Terminal (demo)</option>
                            <option value="Other location in Panabo (demo)">Other location in Panabo (demo)</option>
                        </select>
                    </div>
                </div>
                <p class="booking-form-error" role="alert" data-trip-error></p>
                <p class="booking-helper">Return time is the exclusive end of the rental, so another booking may begin at that exact time.</p>
                <div class="booking-step-actions">
                    <button class="booking-action-button is-secondary" type="button" data-booking-back>Back</button>
                    <button class="booking-action-button is-primary" type="submit">Continue to your details <i class="bi bi-arrow-up-right" aria-hidden="true"></i></button>
                </div>
            </form>
        </div>

        <div class="booking-step-panel" data-booking-step="2" aria-labelledby="booking-customer-heading" hidden inert>
            <div class="booking-step-heading">
                <div>
                    <p class="booking-step-kicker">Step 03 <span>/ 04</span></p>
                    <h3 id="booking-customer-heading">A little about you.</h3>
                    <p>Enter the contact details for this booking request.</p>
                </div>
            </div>

            <form class="booking-form-step" data-booking-customer-form novalidate>
                <div class="booking-fields-grid">
                    <div class="booking-field">
                        <label for="customer-name">Full name <span>*</span></label>
                        <input id="customer-name" name="full_name" type="text" autocomplete="name" maxlength="100" required placeholder="Your full name" value="{{ $bookingCustomer?->name }}">
                    </div>
                    <div class="booking-field">
                        <label for="customer-email">Email address <span>*</span></label>
                        <input id="customer-email" name="email" type="email" autocomplete="email" maxlength="150" required placeholder="you@example.com" value="{{ $bookingCustomer?->email }}" @if ($bookingCustomer) readonly aria-describedby="customer-email-note" @endif>
                        @if ($bookingCustomer)
                            <small class="booking-account-note" id="customer-email-note">This email is fixed to your customer account.</small>
                        @endif
                    </div>
                    <div class="booking-field">
                        <label for="customer-phone">Mobile number <span>*</span></label>
                        <input id="customer-phone" name="mobile" type="tel" autocomplete="tel" inputmode="tel" minlength="7" maxlength="20" pattern="[0-9+ .]{7,20}" title="Enter a Philippine mobile number. Spaces and periods are allowed." required placeholder="+63 9XX XXX XXXX">
                    </div>
                    <div class="booking-field booking-field-wide">
                        <label for="customer-notes">Additional notes <span class="booking-optional">(optional)</span></label>
                        <textarea id="customer-notes" name="notes" rows="3" maxlength="500" placeholder="Anything you'd like to include in your request?"></textarea>
                    </div>
                </div>
                <section class="booking-identity-panel" aria-labelledby="booking-identity-heading">
                    <div class="booking-identity-heading">
                        <span class="booking-identity-icon" aria-hidden="true"><i class="bi bi-shield-check"></i></span>
                        <div>
                            <p class="booking-identity-eyebrow">Optional identity check</p>
                            <h4 id="booking-identity-heading">Give your request a stronger start.</h4>
                            <p>Clear ID photos can help our team verify your details sooner. They don't guarantee approval.</p>
                        </div>
                        <span class="booking-identity-optional">Optional</span>
                    </div>
                    <p class="booking-identity-privacy">
                        <i class="bi bi-lock" aria-hidden="true"></i>
                        Only authorized booking reviewers can view your uploads. JPG, PNG, or WebP · up to 5 MB each.
                    </p>
                    <div class="booking-identity-upload-grid">
                        <label class="booking-identity-upload" for="license-front">
                            <span class="booking-identity-upload-icon" aria-hidden="true"><i class="bi bi-card-image"></i></span>
                            <span class="booking-identity-upload-title">License · front</span>
                            <span class="booking-identity-upload-hint">A clear, readable photo</span>
                            <span class="booking-identity-file-name" data-booking-document-name>Choose a photo</span>
                            <input id="license-front" name="license_front" type="file" accept="image/jpeg,image/png,image/webp" data-booking-document>
                        </label>
                        <label class="booking-identity-upload" for="license-back">
                            <span class="booking-identity-upload-icon" aria-hidden="true"><i class="bi bi-card-image"></i></span>
                            <span class="booking-identity-upload-title">License · back</span>
                            <span class="booking-identity-upload-hint">Include all details in frame</span>
                            <span class="booking-identity-file-name" data-booking-document-name>Choose a photo</span>
                            <input id="license-back" name="license_back" type="file" accept="image/jpeg,image/png,image/webp" data-booking-document>
                        </label>
                        <label class="booking-identity-upload" for="license-selfie">
                            <span class="booking-identity-upload-icon" aria-hidden="true"><i class="bi bi-person-bounding-box"></i></span>
                            <span class="booking-identity-upload-title">You holding your license</span>
                            <span class="booking-identity-upload-hint">Keep your face and ID visible</span>
                            <span class="booking-identity-file-name" data-booking-document-name>Choose a photo</span>
                            <input id="license-selfie" name="license_selfie" type="file" accept="image/jpeg,image/png,image/webp" data-booking-document>
                        </label>
                    </div>
                </section>
                <p class="booking-form-error" role="alert" data-customer-error></p>
                <div class="booking-step-actions">
                    <button class="booking-action-button is-secondary" type="button" data-booking-back>Back</button>
                    <button class="booking-action-button is-primary" type="submit">Review your trip <i class="bi bi-arrow-up-right" aria-hidden="true"></i></button>
                </div>
            </form>
        </div>

        <div class="booking-step-panel" data-booking-step="3" aria-labelledby="booking-review-heading" hidden inert>
            <div class="booking-step-heading">
                <div>
                    <p class="booking-step-kicker">Step 04 <span>/ 04</span></p>
                    <h3 id="booking-review-heading">Review your plans.</h3>
                    <p>The estimate uses displayed rates. The server calculates the request total using the vehicle's stored rate.</p>
                </div>
            </div>

            <div class="booking-review-grid">
                <div class="booking-review-main">
                    <section class="booking-review-block">
                        <div class="booking-review-block-heading">
                            <h4>Vehicle</h4>
                            <button type="button" data-booking-edit="0">Edit</button>
                        </div>
                        <div class="booking-review-vehicle">
                            <img src="" alt="" width="1000" height="650" data-review-image hidden>
                            <span class="booking-review-image-placeholder" data-review-image-placeholder aria-hidden="true"><i class="bi bi-car-front"></i></span>
                            <div>
                                <span class="booking-review-category" data-review-category></span>
                                <strong data-review-vehicle></strong>
                                <span data-review-specs></span>
                            </div>
                        </div>
                    </section>
                    <section class="booking-review-block">
                        <div class="booking-review-block-heading">
                            <h4>Trip details</h4>
                            <button type="button" data-booking-edit="1">Edit</button>
                        </div>
                        <dl class="booking-review-details">
                            <div><dt>Pickup</dt><dd data-review-pickup></dd></div>
                            <div><dt>Return</dt><dd data-review-return></dd></div>
                            <div><dt>Rental duration</dt><dd data-review-duration></dd></div>
                        </dl>
                    </section>
                    <section class="booking-review-block">
                        <div class="booking-review-block-heading">
                            <h4>Customer</h4>
                            <button type="button" data-booking-edit="2">Edit</button>
                        </div>
                        <dl class="booking-review-details">
                            <div><dt>Name</dt><dd data-review-name></dd></div>
                            <div><dt>Email</dt><dd data-review-email></dd></div>
                            <div><dt>Mobile</dt><dd data-review-mobile></dd></div>
                            <div data-review-documents-row hidden><dt>Identity photos</dt><dd data-review-documents></dd></div>
                            <div data-review-notes-row hidden><dt>Notes</dt><dd data-review-notes></dd></div>
                        </dl>
                    </section>
                </div>
                <aside class="booking-review-total">
                    <p class="booking-review-total-label">Estimated rental subtotal</p>
                    <p class="booking-review-price"><strong data-review-total></strong><span>PHP</span></p>
                    <p><span data-review-total-days></span> × <span data-review-day-rate></span> / day</p>
                    <p class="booking-review-caveat">The final subtotal is calculated by the server. No taxes, deposits, insurance, or other charges are included.</p>
                    <fieldset class="booking-review-agreements">
                        <legend>Required agreements</legend>
                        <label>
                            <input type="checkbox" data-legal-agreement data-policy-slugs="terms-and-conditions" data-policy-version-ids="{{ $agreementVersions['terms-and-conditions'] ?? '' }}" aria-describedby="booking-agreement-note">
                            <span>I have read and agree to the <a href="{{ route('legal.show', 'terms-and-conditions') }}" target="_blank" rel="noopener">Terms &amp; Conditions</a>.</span>
                        </label>
                        <label>
                            <input type="checkbox" data-legal-agreement data-policy-slugs="rental-policy" data-policy-version-ids="{{ $agreementVersions['rental-policy'] ?? '' }}" aria-describedby="booking-agreement-note">
                            <span>I have read and understood the <a href="{{ route('legal.show', 'rental-policy') }}" target="_blank" rel="noopener">Rental Policy</a>.</span>
                        </label>
                        <label>
                            <input type="checkbox" data-legal-agreement data-policy-slugs="cancellation-policy,refund-policy" data-policy-version-ids="{{ ($agreementVersions['cancellation-policy'] ?? '').','.($agreementVersions['refund-policy'] ?? '') }}" aria-describedby="booking-agreement-note">
                            <span>I acknowledge the <a href="{{ route('legal.show', 'cancellation-policy') }}" target="_blank" rel="noopener">Cancellation Policy</a> and <a href="{{ route('legal.show', 'refund-policy') }}" target="_blank" rel="noopener">Refund Policy</a>.</span>
                        </label>
                        <small id="booking-agreement-note">Your acceptance is recorded with this booking request and the versions shown. Policy pages open in a new tab.</small>
                        <p class="booking-form-error" role="alert" data-booking-agreement-error></p>
                    </fieldset>
                    <button class="booking-action-button is-primary booking-confirm-button" type="button" data-booking-confirm>
                        <span data-booking-submit-label>Submit booking request</span> <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
                    </button>
                </aside>
            </div>
            <div class="booking-step-actions">
                <button class="booking-action-button is-secondary" type="button" data-booking-back>Back</button>
            </div>
        </div>

        <div class="booking-step-panel booking-confirmation" data-booking-step="4" aria-labelledby="booking-confirmation-heading" hidden inert>
            <div class="booking-confirmation-mark" aria-hidden="true">✓</div>
            <p class="fleet-eyebrow">Request submitted</p>
            <h3 id="booking-confirmation-heading">Your request<br>is under review.</h3>
            <p class="booking-confirmation-message">
                Your booking request is pending admin approval. It is not a confirmed reservation.
            </p>
            <div class="booking-confirmation-summary">
                <span data-confirmation-reference></span>
                <span data-confirmation-vehicle></span>
                <strong data-confirmation-total></strong>
                <small data-confirmation-duration></small>
                <span data-confirmation-status>Pending approval</span>
            </div>
            <section class="booking-confirmation-prompt" aria-labelledby="guest-account-prompt-title" hidden data-confirmation-guest-prompt>
                <h4 id="guest-account-prompt-title">Want to track or cancel this request?</h4>
                <p>Create an account with the same email address. Your request will be linked after you verify that email.</p>
                <a class="booking-action-button is-primary" href="{{ route('register') }}" data-confirmation-create-account>
                    Create account <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
                </a>
            </section>
            @if ($bookingCustomer && ! $bookingCustomer->hasVerifiedEmail())
                <section class="booking-confirmation-prompt booking-confirmation-prompt--verification" aria-labelledby="verify-booking-email-title" hidden data-confirmation-verify-prompt>
                    <h4 id="verify-booking-email-title">Verify your email to track this request.</h4>
                    <p>Your booking can be linked to My Bookings after your account email is verified.</p>
                    <a class="booking-action-button is-primary" href="{{ route('verification.notice') }}">
                        Verify email address <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
                    </a>
                </section>
            @endif
            <div class="booking-confirmation-actions">
                <a class="booking-action-button is-primary" href="" hidden data-confirmation-details>
                    View booking details <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
                </a>
                <button class="booking-action-button is-secondary" type="button" data-booking-back>Back to review</button>
                <a class="booking-action-button is-primary" href="#fleet">Explore the fleet <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
            </div>
        </div>

        <p class="booking-experience-disclaimer">Booking requests are subject to admin approval. Displayed vehicle rates are estimates; the server calculates the saved subtotal.</p>
    </div>
</section>
