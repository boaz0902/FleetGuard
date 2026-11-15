<section class="fleet-section" id="fleet" aria-labelledby="fleet-title" data-fleet>
    <div class="fleet-shell">
        <div class="fleet-intro">
            <div class="fleet-intro-copy">
                <p class="fleet-eyebrow homepage-section-label"><span><strong>02</strong> / Our collection</span></p>
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
                <button class="fleet-filter" type="button" data-filter="suv" aria-pressed="false">SUV</button>
                <button class="fleet-filter" type="button" data-filter="sedan" aria-pressed="false">Sedan</button>
                <button class="fleet-filter" type="button" data-filter="mpv" aria-pressed="false">MPV</button>
                <button class="fleet-filter" type="button" data-filter="van" aria-pressed="false">Van</button>
            </div>
            <p class="fleet-result-count" aria-live="polite" data-fleet-count>06 vehicles</p>
        </div>

        <div class="fleet-viewport" data-fleet-viewport>
            <div class="fleet-track" data-fleet-track>
                @foreach ($vehicles as $index => $vehicle)
                    @include('components.fleet-card', ['vehicle' => $vehicle, 'index' => $index])
                @endforeach
            </div>
        </div>

        <div class="fleet-progress-row">
            <p class="fleet-sample-note">Sample vehicles, images &amp; rates only. Availability is not verified.</p>
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
            <img src="" alt="" width="1000" height="650" data-dialog-image>
            <span class="vehicle-sample-tag"><span aria-hidden="true"></span>Sample inventory</span>
        </div>
        <div class="vehicle-dialog-content">
            <p class="vehicle-category" data-dialog-category></p>
            <h2 id="vehicle-dialog-title" data-dialog-title></h2>
            <p class="vehicle-dialog-disclaimer">Illustrative sample listing — availability and pricing are not verified.</p>
            <dl class="vehicle-dialog-specs">
                <div><dt>Passengers</dt><dd data-dialog-passengers></dd></div>
                <div><dt>Transmission</dt><dd data-dialog-transmission></dd></div>
                <div><dt>Fuel type</dt><dd data-dialog-fuel></dd></div>
                <div><dt>Category</dt><dd data-dialog-category-value></dd></div>
            </dl>
            <div class="vehicle-dialog-footer">
                <p class="vehicle-price"><strong data-dialog-price></strong><span>/ day</span><small>Sample rate</small></p>
                <a class="button button-primary vehicle-booking-link" href="#booking" data-dialog-booking-link>
                    Continue to booking <i class="bi bi-arrow-up-right button-arrow" aria-hidden="true"></i>
                </a>
            </div>
        </div>
    </div>
</dialog>
