<section class="fleet-section" id="fleet" aria-labelledby="fleet-title" data-fleet>
    <div class="fleet-shell">
        <div class="fleet-intro">
            <div class="fleet-intro-copy">
                <p class="fleet-eyebrow homepage-section-label"><span><strong>01</strong> / Our fleet</span></p>
                <h2 id="fleet-title">Find your<br class="fleet-title-break"> perfect ride.</h2>
                <p class="fleet-description">
                    From comfortable city drives to spacious journeys, discover the right vehicle for every destination.
                </p>
            </div>
            <p class="fleet-editorial-note"><span>Curated for the journey</span><span>Panabo City · Philippines</span></p>
        </div>

        <div class="fleet-controls">
            <div class="fleet-filters" role="group" aria-label="Filter vehicles by category" data-fleet-filters>
                <button class="fleet-filter is-active" type="button" data-filter="all" aria-pressed="true">All vehicles</button>
                @foreach ($categories as $category)
                    <button class="fleet-filter" type="button" data-filter="{{ $category }}" aria-pressed="false">{{ strtoupper($category) }}</button>
                @endforeach
            </div>
            <p class="fleet-result-count" aria-live="polite" data-fleet-count>{{ str_pad($vehicles->count(), 2, '0', STR_PAD_LEFT) }} {{ $vehicles->count() === 1 ? 'vehicle' : 'vehicles' }}</p>
        </div>

        <div class="fleet-viewport" data-fleet-viewport>
            <div class="fleet-track" data-fleet-track>
                @foreach ($vehicles as $index => $vehicle)
                    @include('components.fleet-card', ['vehicle' => $vehicle, 'index' => $index])
                @endforeach
            </div>
            @if ($vehicles->isEmpty())
                <p class="fleet-empty-state">No vehicles are currently listed. Please check back soon.</p>
            @endif
        </div>

        <div class="fleet-progress-row">
            <p class="fleet-sample-note">Availability is checked against current bookings and maintenance schedules.</p>
            <div class="fleet-progress" role="progressbar" aria-label="Fleet showcase scroll progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" data-fleet-progress>
                <span data-fleet-progress-fill></span>
            </div>
            <span class="fleet-progress-value" aria-hidden="true" data-fleet-progress-value>00</span>
        </div>
    </div>
</section>

<dialog class="vehicle-dialog" aria-labelledby="vehicle-dialog-title" data-vehicle-dialog>
    <div class="vehicle-dialog-panel" data-vehicle-dialog-panel>
        <button class="vehicle-dialog-close" type="button" aria-label="Close vehicle details" data-dialog-close>
            <i class="bi bi-x-lg" aria-hidden="true"></i>
        </button>
        <div class="vehicle-dialog-image">
            <img src="" alt="" width="1000" height="650" data-dialog-image hidden>
            <span class="vehicle-dialog-image-placeholder" data-dialog-image-placeholder aria-hidden="true"><i class="bi bi-car-front"></i></span>
            <span class="vehicle-sample-tag"><span aria-hidden="true"></span>Fleet vehicle</span>
        </div>
        <div class="vehicle-dialog-content">
            <p class="vehicle-category" data-dialog-category></p>
            <h2 id="vehicle-dialog-title" data-dialog-title></h2>
            <p class="vehicle-dialog-disclaimer">Schedule information is current as of now. Availability is checked again for your selected dates and when you submit a request.</p>
            <section class="vehicle-dialog-availability" aria-labelledby="vehicle-dialog-availability-title">
                <div class="vehicle-dialog-availability-heading">
                    <h3 id="vehicle-dialog-availability-title">Availability schedule</h3>
                    <span>Next 90 days</span>
                </div>
                <p class="vehicle-dialog-schedule-status" role="status" aria-live="polite" data-dialog-schedule-status>Open vehicle details to load the latest schedule.</p>
                <ul class="vehicle-dialog-schedule-list" data-dialog-schedule-list></ul>
                <p class="vehicle-dialog-schedule-note">Times shown in Asia/Manila. Booking requests are not confirmed until approved.</p>
            </section>
            <dl class="vehicle-dialog-specs">
                <div><dt>Passengers</dt><dd data-dialog-passengers></dd></div>
                <div><dt>Transmission</dt><dd data-dialog-transmission></dd></div>
                <div><dt>Fuel type</dt><dd data-dialog-fuel></dd></div>
                <div><dt>Category</dt><dd data-dialog-category-value></dd></div>
            </dl>
            <div class="vehicle-dialog-footer">
                <p class="vehicle-price"><strong data-dialog-price></strong><span>/ day</span><small>Current rate</small></p>
                <a class="button button-primary vehicle-booking-link" href="#booking" data-dialog-booking-link>
                    Continue to booking <i class="bi bi-arrow-up-right button-arrow" aria-hidden="true"></i>
                </a>
            </div>
        </div>
    </div>
</dialog>
