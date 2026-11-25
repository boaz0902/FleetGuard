import { gsap } from 'gsap';

const experience = document.querySelector('[data-booking-experience]');

if (experience) {
    const panels = [...experience.querySelectorAll('[data-booking-step]')];
    const indicators = [...experience.querySelectorAll('[data-step-indicator]')];
    const progress = experience.querySelector('[data-booking-progress]');
    let vehicleOptions = [...experience.querySelectorAll('[data-booking-vehicle]')];
    const tripForm = experience.querySelector('[data-booking-trip-form]');
    const customerForm = experience.querySelector('[data-booking-customer-form]');
    const documentInputs = [...experience.querySelectorAll('[data-booking-document]')];
    const tripError = experience.querySelector('[data-trip-error]');
    const customerError = experience.querySelector('[data-customer-error]');
    const submissionError = experience.querySelector('[data-booking-submit-error]');
    const agreementError = experience.querySelector('[data-booking-agreement-error]');
    const agreementInputs = [...experience.querySelectorAll('[data-legal-agreement]')];
    const confirmButton = experience.querySelector('[data-booking-confirm]');
    const submitLabel = experience.querySelector('[data-booking-submit-label]');
    const searchPreference = experience.querySelector('[data-search-preference]');
    const pickupLocation = experience.querySelector('#trip-pickup-location');
    const returnLocation = experience.querySelector('#trip-return-location');
    const pickupDate = experience.querySelector('[data-trip-pickup-date]');
    const pickupTime = experience.querySelector('#trip-pickup-time');
    const returnDate = experience.querySelector('[data-trip-return-date]');
    const returnTime = experience.querySelector('#trip-return-time');
    const vehicleGrid = experience.querySelector('[data-available-vehicles]');
    const availabilityStatus = experience.querySelector('[data-availability-status]');
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const moneyFormat = new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        maximumFractionDigits: 0,
    });

    const state = {
        step: 0,
        vehicleId: '',
        pickupLocation: '',
        returnLocation: '',
        pickupDate: '',
        pickupTime: '',
        returnDate: '',
        returnTime: '',
        fullName: '',
        email: '',
        mobile: '',
        notes: '',
        bookingReference: '',
    };
    let isSubmitting = false;
    let availabilityRequest = null;
    let availabilitySequence = 0;
    let availabilityTimer;
    let requestedVehicleId = '';
    let autoAdvanceToTripDetails = false;

    const getToday = () => {
        const parts = new Intl.DateTimeFormat('en-CA', {
            timeZone: 'Asia/Manila',
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
        }).formatToParts(new Date());
        const values = Object.fromEntries(parts.map(({ type, value }) => [type, value]));

        return `${values.year}-${values.month}-${values.day}`;
    };

    const selectedVehicle = () => vehicleOptions.find((option) => option.dataset.vehicleId === state.vehicleId);

    const rentalDays = () => {
        if (!state.pickupDate || !state.returnDate) {
            return 1;
        }

        const pickup = new Date(`${state.pickupDate}T${state.pickupTime || '00:00'}:00Z`);
        const returned = new Date(`${state.returnDate}T${state.returnTime || '00:00'}:00Z`);
        const dayDifference = Math.ceil((returned - pickup) / 86_400_000);

        return Number.isFinite(dayDifference) ? Math.max(1, dayDifference) : 1;
    };

    const estimate = () => {
        const vehicle = selectedVehicle();

        return vehicle ? Number(vehicle.dataset.dailyRate) * rentalDays() : 0;
    };

    const syncCustomerState = () => {
        if (!customerForm) {
            return;
        }

        const values = new FormData(customerForm);
        state.fullName = String(values.get('full_name') ?? '').trim();
        state.email = String(values.get('email') ?? '').trim();
        state.mobile = String(values.get('mobile') ?? '').trim();
        state.notes = String(values.get('notes') ?? '').trim();
    };

    const syncTripState = () => {
        const values = new FormData(tripForm);
        state.pickupLocation = String(values.get('pickup_location') ?? '');
        state.returnLocation = String(values.get('return_location') ?? '');
        state.pickupDate = pickupDate.value;
        state.pickupTime = pickupTime.value;
        state.returnDate = returnDate.value;
        state.returnTime = returnTime.value;
    };

    const addDemoLocation = (select, value) => {
        if (!value || [...select.options].some((option) => option.value === value)) {
            return;
        }

        const option = new Option(`${value} (demo entry)`, value);
        select.add(option, 1);
    };

    const setVehicle = (id) => {
        const vehicle = vehicleOptions.find((option) => option.dataset.vehicleId === id);

        if (!vehicle) {
            return false;
        }

        state.vehicleId = id;
        submissionError.textContent = '';
        vehicleOptions.forEach((option) => {
            const isSelected = option === vehicle;

            option.classList.toggle('is-selected', isSelected);
            option.setAttribute('aria-pressed', String(isSelected));
        });

        experience.querySelector('[data-booking-next]').disabled = false;
        return true;
    };

    const renderAvailableVehicles = (vehicles) => {
        vehicleGrid.replaceChildren();
        vehicleOptions = [];

        vehicles.forEach((vehicle) => {
            const option = document.createElement('button');
            option.className = 'booking-vehicle-option';
            option.type = 'button';
            option.dataset.bookingVehicle = '';
            option.dataset.vehicleId = String(vehicle.id);
            option.dataset.name = String(vehicle.name);
            option.dataset.categoryKey = String(vehicle.category);
            option.dataset.category = String(vehicle.category_label);
            option.dataset.passengers = String(vehicle.passengers);
            option.dataset.transmission = String(vehicle.transmission);
            option.dataset.fuel = String(vehicle.fuel);
            option.dataset.dailyRate = String(vehicle.daily_rate);
            option.dataset.image = '';
            option.setAttribute('aria-pressed', 'false');

            if (vehicle.image_url) {
                const imageUrl = new URL(vehicle.image_url, window.location.origin);

                if (imageUrl.origin === window.location.origin) {
                    const image = document.createElement('img');
                    image.src = imageUrl.href;
                    image.alt = '';
                    image.width = 1000;
                    image.height = 650;
                    image.loading = 'lazy';
                    image.decoding = 'async';
                    option.dataset.image = imageUrl.href;
                    option.append(image);
                }
            }

            const body = document.createElement('span');
            body.className = 'booking-vehicle-option-body';

            const category = document.createElement('span');
            category.className = 'booking-vehicle-option-category';
            category.textContent = `${vehicle.category_label} · Available`;

            const name = document.createElement('span');
            name.className = 'booking-vehicle-option-name';
            name.textContent = vehicle.name;

            const rate = document.createElement('span');
            rate.className = 'booking-vehicle-option-rate';
            rate.append(document.createTextNode(moneyFormat.format(Number(vehicle.daily_rate))));

            const rateCaption = document.createElement('small');
            rateCaption.textContent = ' / day · database rate';
            rate.append(rateCaption);
            body.append(category, name, rate);
            option.append(body);

            const check = document.createElement('span');
            check.className = 'booking-vehicle-check';
            check.setAttribute('aria-hidden', 'true');
            check.textContent = '✓';
            option.append(check);

            option.addEventListener('click', () => setVehicle(option.dataset.vehicleId));
            vehicleGrid.append(option);
            vehicleOptions.push(option);
        });

        const selected = vehicleOptions.find((option) => option.dataset.vehicleId === state.vehicleId);

        if (selected) {
            setVehicle(selected.dataset.vehicleId);
        } else {
            state.vehicleId = '';
            experience.querySelector('[data-booking-next]').disabled = true;
        }
    };

    const searchAvailability = async () => {
        const sequence = ++availabilitySequence;
        availabilityRequest?.abort();
        availabilityRequest = null;
        renderAvailableVehicles([]);
        availabilityStatus.classList.remove('is-error', 'is-loading');

        if (!pickupDate.value || !pickupTime.value || !returnDate.value || !returnTime.value) {
            availabilityStatus.textContent = 'Choose pickup and return dates and times to check live availability.';
            return;
        }

        if (
            returnDate.value < pickupDate.value
            || (returnDate.value === pickupDate.value && returnTime.value <= pickupTime.value)
        ) {
            availabilityStatus.textContent = 'Return date and time must be later than pickup.';
            availabilityStatus.classList.add('is-error');
            return;
        }

        availabilityRequest = new AbortController();
        const params = new URLSearchParams({
            pickup_date: pickupDate.value,
            pickup_time: pickupTime.value,
            return_date: returnDate.value,
            return_time: returnTime.value,
        });
        availabilityStatus.textContent = 'Checking live vehicle availability…';
        availabilityStatus.classList.add('is-loading');

        try {
            const response = await fetch(`${experience.dataset.availabilityUrl}?${params}`, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
                signal: availabilityRequest.signal,
            });

            if (sequence !== availabilitySequence) {
                return;
            }

            let result;

            try {
                result = await response.json();
            } catch {
                throw new Error('The availability service returned an unexpected response.');
            }

            if (sequence !== availabilitySequence) {
                return;
            }

            if (response.status === 422) {
                const messages = Object.values(result.errors ?? {}).flat();
                throw new Error(messages.join(' ') || 'Check the selected pickup and return times.');
            }

            if (response.status === 429) {
                throw new Error('Too many availability searches. Please wait a moment and try again.');
            }

            if (!response.ok || !Array.isArray(result.data)) {
                throw new Error('Availability could not be checked. Please try again.');
            }

            renderAvailableVehicles(result.data);
            availabilityStatus.classList.remove('is-loading');
            availabilityStatus.textContent = result.data.length
                ? `${result.data.length} vehicle${result.data.length === 1 ? '' : 's'} available for these dates.`
                : 'No vehicles are available for this date and time range.';

            if (autoAdvanceToTripDetails) {
                const requestedVehicle = vehicleOptions.find(
                    (option) => option.dataset.vehicleId === requestedVehicleId,
                );
                autoAdvanceToTripDetails = false;

                if (requestedVehicle) {
                    setVehicle(requestedVehicleId);
                    showBooking(1);
                } else {
                    availabilityStatus.textContent =
                        'The selected vehicle is no longer available for these dates. Choose another available vehicle or change your dates.';
                }
            }
        } catch (error) {
            if (error.name === 'AbortError' || sequence !== availabilitySequence) {
                return;
            }

            renderAvailableVehicles([]);
            availabilityStatus.classList.remove('is-loading');
            availabilityStatus.classList.add('is-error');
            availabilityStatus.textContent = error instanceof TypeError
                ? 'Could not reach the availability service. Check your connection and try again.'
                : error.message;
        }
    };

    const scheduleAvailabilitySearch = () => {
        window.clearTimeout(availabilityTimer);
        availabilityRequest?.abort();
        availabilitySequence++;
        renderAvailableVehicles([]);
        availabilityStatus.classList.remove('is-error', 'is-loading');
        availabilityStatus.textContent = 'Updating live availability…';
        availabilityStatus.classList.add('is-loading');
        const scheduledSequence = availabilitySequence + 1;
        availabilityTimer = window.setTimeout(() => {
            void searchAvailability().finally(() => {
                if (scheduledSequence === availabilitySequence) {
                    window.dispatchEvent(new Event('fleetguard:availability-search-settled'));
                }
            });
        }, 250);
    };

    const updateStepper = (step) => {
        const currentIndicator = Math.min(step, indicators.length - 1);

        indicators.forEach((indicator, index) => {
            const isCurrent = index === currentIndicator;
            const isComplete = step === panels.length - 1 || index < step;

            indicator.classList.toggle('is-current', isCurrent);
            indicator.classList.toggle('is-complete', isComplete && !isCurrent);

            if (isCurrent) {
                indicator.setAttribute('aria-current', 'step');
            } else {
                indicator.removeAttribute('aria-current');
            }
        });

        progress.style.width = `${Math.min(100, (step / (indicators.length - 1)) * 100)}%`;
    };

    const presentStep = (step, { scroll = false } = {}) => {
        const currentPanel = panels[state.step];
        const nextPanel = panels[step];

        if (!nextPanel) {
            return;
        }

        if (step === state.step) {
            if (scroll) {
                experience.scrollIntoView({
                    behavior: reduceMotion ? 'instant' : 'smooth',
                    block: 'start',
                });
            }

            return;
        }

        const activate = () => {
            panels.forEach((panel, index) => {
                const isActive = index === step;

                panel.hidden = !isActive;
                panel.inert = !isActive;
                panel.classList.toggle('is-active', isActive);
            });

            state.step = step;
            updateStepper(step);

            if (!reduceMotion) {
                gsap.fromTo(
                    nextPanel,
                    { autoAlpha: 0, y: 12 },
                    { autoAlpha: 1, y: 0, duration: 0.28, ease: 'power2.out' },
                );
            }

            if (scroll) {
                experience.scrollIntoView({
                    behavior: reduceMotion ? 'instant' : 'smooth',
                    block: 'start',
                });
            }
        };

        if (reduceMotion || currentPanel.hidden) {
            activate();
            return;
        }

        gsap.to(currentPanel, {
            autoAlpha: 0,
            y: -8,
            duration: 0.14,
            ease: 'power1.in',
            onComplete: activate,
        });
    };

    const writeReview = () => {
        const vehicle = selectedVehicle();

        if (!vehicle) {
            return;
        }

        const reviewImage = experience.querySelector('[data-review-image]');
        const reviewImagePlaceholder = experience.querySelector('[data-review-image-placeholder]');

        if (vehicle.dataset.image) {
            reviewImage.src = vehicle.dataset.image;
            reviewImage.alt = vehicle.dataset.name;
            reviewImage.hidden = false;
            reviewImagePlaceholder.hidden = true;
        } else {
            reviewImage.removeAttribute('src');
            reviewImage.alt = '';
            reviewImage.hidden = true;
            reviewImagePlaceholder.hidden = false;
        }

        experience.querySelector('[data-review-category]').textContent = vehicle.dataset.category;
        experience.querySelector('[data-review-vehicle]').textContent = vehicle.dataset.name;
        experience.querySelector('[data-review-specs]').textContent =
            `${vehicle.dataset.passengers} · ${vehicle.dataset.transmission} · ${vehicle.dataset.fuel}`;

        experience.querySelector('[data-review-pickup]').textContent =
            `${state.pickupLocation} · ${state.pickupDate} · ${state.pickupTime}`;
        experience.querySelector('[data-review-return]').textContent =
            `${state.returnLocation} · ${state.returnDate} · ${state.returnTime}`;
        experience.querySelector('[data-review-duration]').textContent =
            `${rentalDays()} ${rentalDays() === 1 ? 'day' : 'days'} (minimum 1 day)`;
        experience.querySelector('[data-review-name]').textContent = state.fullName;
        experience.querySelector('[data-review-email]').textContent = state.email;
        experience.querySelector('[data-review-mobile]').textContent = state.mobile;
        const documentCount = documentInputs.filter((input) => input.files?.length).length;
        experience.querySelector('[data-review-documents]').textContent = `${documentCount} of ${documentInputs.length} added`;
        experience.querySelector('[data-review-documents-row]').hidden = documentCount === 0;
        experience.querySelector('[data-review-notes]').textContent = state.notes || '—';
        experience.querySelector('[data-review-notes-row]').hidden = !state.notes;
        experience.querySelector('[data-review-total]').textContent = moneyFormat.format(estimate());
        experience.querySelector('[data-review-total-days]').textContent =
            `${rentalDays()} ${rentalDays() === 1 ? 'day' : 'days'}`;
        experience.querySelector('[data-review-day-rate]').textContent = moneyFormat.format(Number(vehicle.dataset.dailyRate));
    };

    const writeConfirmation = (booking) => {
        const vehicle = selectedVehicle();

        if (!vehicle || !booking) {
            return;
        }

        const rentalDays = Number(booking.rental_duration_days);
        const estimatedTotal = Number(booking.estimated_total);

        if (!Number.isInteger(rentalDays) || rentalDays < 1 || !Number.isFinite(estimatedTotal)) {
            return;
        }

        experience.querySelector('[data-confirmation-reference]').textContent =
            `Booking reference: ${booking.booking_reference}`;
        experience.querySelector('[data-confirmation-vehicle]').textContent = vehicle.dataset.name;
        experience.querySelector('[data-confirmation-total]').textContent = moneyFormat.format(estimatedTotal);
        experience.querySelector('[data-confirmation-duration]').textContent =
            `${rentalDays} ${rentalDays === 1 ? 'rental day' : 'rental days'}`;
        experience.querySelector('[data-confirmation-status]').textContent = 'Status: Pending approval';

        const detailsLink = experience.querySelector('[data-confirmation-details]');

        if (detailsLink instanceof HTMLAnchorElement && typeof booking.details_url === 'string') {
            detailsLink.href = booking.details_url;
            detailsLink.hidden = false;
        } else if (experience.dataset.customerAccountState === 'guest') {
            const guestPrompt = experience.querySelector('[data-confirmation-guest-prompt]');
            const createAccountLink = experience.querySelector('[data-confirmation-create-account]');

            if (guestPrompt instanceof HTMLElement && createAccountLink instanceof HTMLAnchorElement) {
                const registrationUrl = new URL(experience.dataset.registerUrl, window.location.origin);
                registrationUrl.searchParams.set('email', state.email);
                createAccountLink.href = registrationUrl.toString();
                guestPrompt.hidden = false;
            }
        } else if (experience.dataset.customerAccountState === 'unverified') {
            const verifyPrompt = experience.querySelector('[data-confirmation-verify-prompt]');

            if (verifyPrompt instanceof HTMLElement) {
                verifyPrompt.hidden = false;
            }
        }
    };

    const showValidationErrors = (errors = {}) => {
        const messages = Object.values(errors)
            .flat()
            .filter((message) => typeof message === 'string');

        submissionError.textContent = messages.join(' ');
        tripError.textContent = '';
        customerError.textContent = '';

        const tripFields = new Set([
            'pickup_location',
            'return_location',
        ]);
        const dateFields = new Set([
            'pickup_date',
            'pickup_time',
            'return_date',
            'return_time',
        ]);
        const customerFields = new Set([
            'full_name',
            'email',
            'mobile_number',
            'customer_notes',
            'license_front',
            'license_back',
            'license_selfie',
        ]);
        const tripMessages = Object.entries(errors)
            .filter(([field]) => tripFields.has(field))
            .flatMap(([, fieldMessages]) => fieldMessages);
        const dateMessages = Object.entries(errors)
            .filter(([field]) => dateFields.has(field))
            .flatMap(([, fieldMessages]) => fieldMessages);
        const customerMessages = Object.entries(errors)
            .filter(([field]) => customerFields.has(field))
            .flatMap(([, fieldMessages]) => fieldMessages);

        if (dateMessages.length > 0) {
            availabilityStatus.textContent = dateMessages.join(' ');
            availabilityStatus.classList.add('is-error');
            presentStep(0, { scroll: true });
        } else if (tripMessages.length > 0) {
            tripError.textContent = tripMessages.join(' ');
            presentStep(1, { scroll: true });
        } else if (customerMessages.length > 0) {
            customerError.textContent = customerMessages.join(' ');
            presentStep(2, { scroll: true });
        } else if (Object.keys(errors).some((field) => field.startsWith('agreements.') || field.startsWith('policy_versions.'))) {
            agreementError.textContent = messages.join(' ') || 'Review and accept each required policy before submitting.';
            presentStep(3, { scroll: true });
            agreementInputs.find((input) => !input.checked)?.focus();
        } else if (errors.vehicle_id) {
            presentStep(0, { scroll: true });
        } else if (messages.length === 0) {
            submissionError.textContent = 'Some booking details need attention. Review your information and try again.';
        }
    };

    const submitBooking = async () => {
        if (isSubmitting || state.bookingReference) {
            return;
        }

        const missingAgreement = agreementInputs.find((input) => !input.checked);

        if (missingAgreement) {
            agreementError.textContent = 'Check all three required agreements before submitting your booking request.';
            missingAgreement.focus();
            return;
        }

        agreementError.textContent = '';
        const agreements = {};
        const policyVersions = {};

        agreementInputs.forEach((input) => {
            const slugs = input.dataset.policySlugs.split(',');
            const versionIds = input.dataset.policyVersionIds.split(',');
            slugs.forEach((slug, index) => {
                agreements[slug] = true;
                policyVersions[slug] = versionIds[index];
            });
        });

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

        if (!csrfToken) {
            submissionError.textContent = 'Your security token is missing. Refresh the page before submitting again.';
            return;
        }

        isSubmitting = true;
        confirmButton.disabled = true;
        confirmButton.setAttribute('aria-busy', 'true');
        submitLabel.textContent = 'Submitting request…';
        submissionError.textContent = '';

        try {
            let response;
            const payload = new FormData();
            const bookingFields = {
                vehicle_id: state.vehicleId,
                full_name: state.fullName,
                email: state.email,
                mobile_number: state.mobile.replace(/[ .]/g, ''),
                pickup_location: state.pickupLocation,
                pickup_date: state.pickupDate,
                pickup_time: state.pickupTime,
                return_location: state.returnLocation,
                return_date: state.returnDate,
                return_time: state.returnTime,
                customer_notes: state.notes,
            };

            Object.entries(bookingFields).forEach(([key, value]) => payload.append(key, value));
            Object.entries(agreements).forEach(([slug, value]) => payload.append(`agreements[${slug}]`, String(value)));
            Object.entries(policyVersions).forEach(([slug, value]) => payload.append(`policy_versions[${slug}]`, value));
            documentInputs.forEach((input) => {
                if (input.files?.[0]) {
                    payload.append(input.name, input.files[0]);
                }
            });

            try {
                response = await fetch(experience.dataset.bookingUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: payload,
                });
            } catch {
                submissionError.textContent = 'We could not reach the booking service. Your details are still here; check your connection and try again.';
                return;
            }

            if (response.status === 403) {
                submissionError.textContent = 'This booking request is not permitted. Refresh the page and try again.';
                return;
            }

            if (response.status === 419) {
                submissionError.textContent = 'Your session expired. Refresh the page before submitting again.';
                return;
            }

            if (response.status === 429) {
                submissionError.textContent = 'Too many booking attempts. Please wait a moment before trying again.';
                return;
            }

            if (response.status >= 500) {
                submissionError.textContent = 'We could not save your request right now. Your details are still here; please try again later.';
                return;
            }

            let result;

            try {
                result = await response.json();
            } catch {
                submissionError.textContent = 'The booking service returned an unexpected response. Your details are still here; please try again.';
                return;
            }

            if (response.status === 422) {
                showValidationErrors(result.errors);
                return;
            }

            if (!response.ok) {
                submissionError.textContent = 'We could not submit your booking request. Review your details and try again.';
                return;
            }

            const booking = result.booking;
            const rentalDays = Number(booking?.rental_duration_days);
            const estimatedTotal = Number(booking?.estimated_total);

            if (
                typeof booking?.booking_reference !== 'string'
                || booking.booking_reference.length === 0
                || booking.vehicle_id !== state.vehicleId
                || booking.status !== 'pending'
                || !Number.isInteger(rentalDays)
                || rentalDays < 1
                || !Number.isFinite(estimatedTotal)
            ) {
                submissionError.textContent = 'The booking service returned incomplete confirmation details. Contact FleetGuard before submitting again.';
                return;
            }

            state.bookingReference = booking.booking_reference;
            writeConfirmation(booking);
            presentStep(4, { scroll: true });
        } finally {
            isSubmitting = false;
            confirmButton.disabled = false;
            confirmButton.removeAttribute('aria-busy');
            submitLabel.textContent = 'Submit booking request';
        }
    };

    const setRentalDates = ({ pickup, returned }) => {
        const today = getToday();

        pickupDate.min = today;
        returnDate.min = today;

        if (pickup) {
            pickupDate.value = pickup;
        }

        if (returned) {
            returnDate.value = returned;
        }

        updateReturnDateMinimum();
    };

    function updateReturnDateMinimum() {
        returnDate.min = pickupDate.value || getToday();
        returnDate.setCustomValidity(
            returnDate.value && pickupDate.value && returnDate.value < pickupDate.value
                ? 'Return date cannot be earlier than the pickup date.'
                : '',
        );
        updateTimeConstraints();
    }

    function getCurrentTime() {
        const parts = new Intl.DateTimeFormat('en-GB', {
            timeZone: 'Asia/Manila',
            hour: '2-digit',
            minute: '2-digit',
            hourCycle: 'h23',
        }).formatToParts(new Date());
        const values = Object.fromEntries(parts.map(({ type, value }) => [type, value]));

        return `${values.hour}:${values.minute}`;
    }

    function updateTimeConstraints() {
        const pickupIsToday = pickupDate.value === getToday();
        const sameRentalDay = Boolean(
            pickupDate.value
            && returnDate.value
            && pickupDate.value === returnDate.value,
        );
        const currentTime = getCurrentTime();

        [...pickupTime.options].forEach((option) => {
            option.disabled = Boolean(option.value && pickupIsToday && option.value <= currentTime);
        });

        if (pickupTime.selectedOptions[0]?.disabled) {
            pickupTime.value = '';
        }

        [...returnTime.options].forEach((option) => {
            option.disabled = Boolean(
                option.value
                && sameRentalDay
                && pickupTime.value
                && option.value <= pickupTime.value,
            );
        });

        if (returnTime.selectedOptions[0]?.disabled) {
            returnTime.value = '';
        }

        pickupTime.setCustomValidity(
            pickupIsToday && pickupTime.value && pickupTime.value <= currentTime
                ? 'Choose a pickup time that has not passed.'
                : '',
        );
        returnTime.setCustomValidity(
            sameRentalDay
            && pickupTime.value
            && returnTime.value
            && returnTime.value <= pickupTime.value
                ? 'Return time must be later than pickup time.'
                : '',
        );
    }

    setRentalDates({});

    const populateFromSearch = (details = {}) => {
        if (details.pickupLocation) {
            addDemoLocation(pickupLocation, details.pickupLocation);
            pickupLocation.value = details.pickupLocation;
            returnLocation.value = details.pickupLocation;
        }

        if (details.pickupDate || details.returnDate) {
            setRentalDates({ pickup: details.pickupDate, returned: details.returnDate });
        }

        pickupTime.value = details.pickupTime || '09:00';
        returnTime.value = details.returnTime || '18:00';
        updateReturnDateMinimum();

        if (details.vehicleCategory && details.vehicleCategory !== 'any') {
            const categoryLabel = {
                suv: 'SUV',
                sedan: 'Sedan',
                mpv: 'MPV',
            }[details.vehicleCategory];

            if (categoryLabel) {
                searchPreference.textContent = `Your search preference: ${categoryLabel}. Choose an available vehicle for your dates.`;
                searchPreference.hidden = false;
                return;
            }
        }

        searchPreference.hidden = true;
    };

    const pageParams = new URLSearchParams(window.location.search);
    if (
        pageParams.get('pickup_date')
        || pageParams.get('return_date')
        || pageParams.get('pickup_location')
        || pageParams.get('vehicle')
    ) {
        populateFromSearch({
            pickupLocation: pageParams.get('pickup_location') || '',
            pickupDate: pageParams.get('pickup_date') || '',
            pickupTime: pageParams.get('pickup_time') || '',
            returnDate: pageParams.get('return_date') || '',
            returnTime: pageParams.get('return_time') || '',
            vehicleCategory: pageParams.get('category') || 'any',
        });

        const requestedVehicle = pageParams.get('vehicle');
        if (requestedVehicle) {
            requestedVehicleId = requestedVehicle;
            autoAdvanceToTripDetails = true;
            window.requestAnimationFrame(() => {
                scheduleAvailabilitySearch();
            });
        }
    }

    const showBooking = (step = 0) => {
        presentStep(step, { scroll: true });
    };

    experience.querySelector('[data-booking-next]').addEventListener('click', () => {
        if (state.vehicleId) {
            presentStep(1);
        }
    });

    experience.querySelectorAll('[data-booking-back]').forEach((button) => {
        button.addEventListener('click', () => {
            if (state.step === 4) {
                presentStep(3);
                return;
            }

            presentStep(Math.max(0, state.step - 1));
        });
    });

    experience.querySelectorAll('[data-booking-edit]').forEach((button) => {
        button.addEventListener('click', () => presentStep(Number(button.dataset.bookingEdit)));
    });

    [pickupDate, pickupTime, returnDate, returnTime].forEach((input) => {
        input.addEventListener('change', () => {
            tripError.textContent = '';
            submissionError.textContent = '';
            updateReturnDateMinimum();
            scheduleAvailabilitySearch();
        });
    });

    tripForm.addEventListener('input', () => {
        tripError.textContent = '';
        submissionError.textContent = '';
    });

    tripForm.addEventListener('change', () => {
        tripError.textContent = '';
        submissionError.textContent = '';
    });

    tripForm.addEventListener('submit', (event) => {
        event.preventDefault();
        tripError.textContent = '';
        updateReturnDateMinimum();

        if (!tripForm.reportValidity()) {
            tripError.textContent = 'Choose a pickup and return location.';
            return;
        }

        syncTripState();
        if (!state.vehicleId || !vehicleOptions.some((option) => option.dataset.vehicleId === state.vehicleId)) {
            tripError.textContent = 'Choose an available vehicle for your selected dates before continuing.';
            presentStep(0, { scroll: true });
            return;
        }
        presentStep(2);
    });

    customerForm.addEventListener('input', () => {
        customerError.textContent = '';
        submissionError.textContent = '';
    });

    documentInputs.forEach((input) => {
        input.addEventListener('change', () => {
            const fileName = input.closest('.booking-identity-upload')?.querySelector('[data-booking-document-name]');

            if (fileName) {
                fileName.textContent = input.files?.[0]?.name ?? 'Choose a photo';
            }

            customerError.textContent = '';
            submissionError.textContent = '';
        });
    });

    customerForm.addEventListener('submit', (event) => {
        event.preventDefault();
        customerError.textContent = '';

        if (!customerForm.reportValidity()) {
            customerError.textContent = 'Check the required fields and enter a valid email address and mobile number.';
            return;
        }

        syncCustomerState();
        writeReview();
        presentStep(3);
    });

    confirmButton.addEventListener('click', submitBooking);

    window.addEventListener('fleetguard:booking-start', (event) => {
        populateFromSearch(event.detail);
        scheduleAvailabilitySearch();
        showBooking(0);
    });

    window.addEventListener('fleetguard:book-vehicle', (event) => {
        const matchedOption = vehicleOptions.find(
            (option) => option.dataset.vehicleId === event.detail?.id,
        );

        searchPreference.hidden = true;
        if (matchedOption) {
            setVehicle(matchedOption.dataset.vehicleId);
        } else {
            searchPreference.textContent = 'Choose your pickup and return dates to check whether this vehicle is available.';
            searchPreference.hidden = false;
        }
        showBooking(0);
    });
}
