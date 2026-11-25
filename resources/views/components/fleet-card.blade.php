<article
    class="vehicle-card"
    data-fleet-card
    data-vehicle-id="{{ $vehicle['id'] }}"
    data-category="{{ $vehicle['category'] }}"
    data-name="{{ $vehicle['name'] }}"
    data-category-label="{{ $vehicle['category_label'] }}"
    data-passengers="{{ $vehicle['passengers'] }}"
    data-transmission="{{ $vehicle['transmission'] }}"
    data-fuel="{{ $vehicle['fuel'] }}"
    data-price="{{ $vehicle['price'] }}"
    data-daily-rate="{{ $vehicle['daily_rate'] }}"
    data-image="{{ $vehicle['image'] ? asset($vehicle['image']) : '' }}"
    data-image-alt="{{ $vehicle['name'] }}"
    data-schedule-url="{{ route('vehicles.schedule', $vehicle['id']) }}"
>
    <div class="vehicle-card-image">
        <button
            class="vehicle-image-button"
            type="button"
            data-vehicle-open
            aria-label="View {{ $vehicle['name'] }} details"
        >
            @if ($vehicle['image'])
                <img
                    src="{{ asset($vehicle['image']) }}"
                    alt="{{ $vehicle['name'] }}"
                    width="1000"
                    height="650"
                    loading="lazy"
                    decoding="async"
                    data-vehicle-image
                >
            @else
                <span class="vehicle-card-image-placeholder" aria-hidden="true"><i class="bi bi-car-front"></i></span>
            @endif
        </button>
        <span class="vehicle-sample-tag"><span aria-hidden="true"></span>Fleet vehicle</span>
        <span class="vehicle-card-number" aria-hidden="true">/ {{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</span>
    </div>

    <div class="vehicle-card-body">
        <div class="vehicle-card-heading">
            <div>
                <p class="vehicle-category">{{ $vehicle['category_label'] }}</p>
                <h3>
                    <button
                        class="vehicle-name-button"
                        type="button"
                        data-vehicle-open
                        aria-label="View {{ $vehicle['name'] }} details"
                    >{{ $vehicle['name'] }}</button>
                </h3>
            </div>
            <i class="bi bi-arrow-up-right vehicle-card-arrow" aria-hidden="true"></i>
        </div>

        <ul class="vehicle-specs" aria-label="Vehicle specifications">
            <li><i class="bi bi-people spec-icon" aria-hidden="true"></i>{{ $vehicle['passengers'] }}</li>
            <li><i class="bi bi-gear-wide-connected spec-icon" aria-hidden="true"></i>{{ $vehicle['transmission'] }}</li>
            <li><i class="bi bi-fuel-pump spec-icon" aria-hidden="true"></i>{{ $vehicle['fuel'] }}</li>
        </ul>

        <div class="vehicle-card-footer">
            <p class="vehicle-price"><strong>{{ $vehicle['price'] }}</strong><span>/ day</span><small>Current rate</small></p>
            <button
                class="vehicle-details-button"
                type="button"
                data-vehicle-details
                aria-label="View {{ $vehicle['name'] }} details"
            >
                View details <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
            </button>
        </div>
    </div>
</article>
