import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

const standaloneFleet = document.querySelector('.showroom-page [data-fleet]');

if (standaloneFleet) {
    const cards = [...standaloneFleet.querySelectorAll('[data-fleet-card]')];
    const filterButtons = [...standaloneFleet.querySelectorAll('[data-filter]')];
    const count = standaloneFleet.querySelector('[data-fleet-count]');

    filterButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const category = button.dataset.filter;

            filterButtons.forEach((item) => {
                const selected = item === button;
                item.classList.toggle('is-active', selected);
                item.setAttribute('aria-pressed', String(selected));
            });

            let visible = 0;
            cards.forEach((card) => {
                const show = category === 'all' || card.dataset.category === category;
                card.hidden = !show;
                if (show) visible += 1;
            });

            if (count) count.textContent = `Showing ${visible} ${visible === 1 ? 'vehicle' : 'vehicles'}`;
        });
    });

    const pickup = document.querySelector('[data-fleet-pickup]');
    const returned = document.querySelector('[data-fleet-return]');

    if (pickup && returned) {
        const today = new Intl.DateTimeFormat('en-CA', {
            timeZone: 'Asia/Manila',
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
        }).format(new Date()).replaceAll('/', '-');
        pickup.min = today;
        returned.min = pickup.value || today;
        const updateMinimum = () => {
            returned.min = pickup.value || today;
            returned.setCustomValidity(
                pickup.value && returned.value && returned.value <= pickup.value
                    ? 'Return date must be after the pickup date.'
                    : '',
            );
        };
        pickup.addEventListener('change', updateMinimum);
        returned.addEventListener('change', updateMinimum);
    }
}

const vehicleBookingForm = document.querySelector('[data-vehicle-booking-form]');

if (vehicleBookingForm instanceof HTMLFormElement) {
    const pickupDate = vehicleBookingForm.querySelector('[data-vehicle-pickup-date]');
    const pickupTime = vehicleBookingForm.querySelector('[data-vehicle-pickup-time]');
    const returnDate = vehicleBookingForm.querySelector('[data-vehicle-return-date]');
    const returnTime = vehicleBookingForm.querySelector('[data-vehicle-return-time]');
    const dateHint = vehicleBookingForm.querySelector('[data-vehicle-date-hint]');
    const availabilityStatus = vehicleBookingForm.querySelector('[data-vehicle-availability]');
    const requestButton = vehicleBookingForm.querySelector('[data-request-vehicle]');

    if (
        pickupDate instanceof HTMLInputElement
        && pickupTime instanceof HTMLSelectElement
        && returnDate instanceof HTMLInputElement
        && returnTime instanceof HTMLSelectElement
    ) {
        let availabilityRequest = null;
        let availabilitySequence = 0;
        let availabilityTimer;

        const getManilaNow = () => {
            const parts = new Intl.DateTimeFormat('en-CA', {
                timeZone: 'Asia/Manila',
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                hourCycle: 'h23',
            }).formatToParts(new Date());
            const values = Object.fromEntries(parts.map(({ type, value }) => [type, value]));

            return {
                date: `${values.year}-${values.month}-${values.day}`,
                time: `${values.hour}:${values.minute}`,
            };
        };
        const refreshDateAndTimeConstraints = () => {
            const now = getManilaNow();
            pickupDate.min = now.date;
            returnDate.min = pickupDate.value || now.date;

            const sameDayAsToday = pickupDate.value === now.date;
            [...pickupTime.options].forEach((option) => {
                option.disabled = Boolean(option.value && sameDayAsToday && option.value <= now.time);
            });

            if (pickupTime.selectedOptions[0]?.disabled) {
                pickupTime.value = '';
            }

            const sameRentalDay = Boolean(pickupDate.value && returnDate.value && pickupDate.value === returnDate.value);
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

            const invalidDateOrder = Boolean(
                pickupDate.value
                && returnDate.value
                && returnDate.value < pickupDate.value,
            );
            const invalidTimeOrder = Boolean(
                sameRentalDay
                && pickupTime.value
                && returnTime.value
                && returnTime.value <= pickupTime.value,
            );
            returnDate.setCustomValidity(invalidDateOrder || invalidTimeOrder
                ? 'Return date and time must be later than pickup.'
                : '');

            if (dateHint) {
                dateHint.textContent = invalidDateOrder || invalidTimeOrder
                    ? 'Choose a return date and time after your pickup.'
                    : 'Pickup times are shown in Asia/Manila. Past dates and times are not available.';
            }
        };

        const checkVehicleAvailability = async () => {
            const sequence = ++availabilitySequence;
            availabilityRequest?.abort();
            availabilityRequest = null;

            if (requestButton instanceof HTMLButtonElement) {
                requestButton.disabled = true;
                requestButton.removeAttribute('aria-busy');
            }

            availabilityStatus?.classList.remove('is-loading', 'is-error', 'is-available', 'is-unavailable');

            if (!pickupDate.value || !pickupTime.value || !returnDate.value || !returnTime.value) {
                if (availabilityStatus) {
                    availabilityStatus.textContent = 'Choose your pickup and return dates and times to check live availability.';
                }
                return;
            }

            const params = new URLSearchParams({
                pickup_date: pickupDate.value,
                pickup_time: pickupTime.value,
                return_date: returnDate.value,
                return_time: returnTime.value,
            });
            availabilityStatus?.classList.add('is-loading');
            if (availabilityStatus) {
                availabilityStatus.textContent = 'Checking live availability…';
            }
            if (requestButton instanceof HTMLButtonElement) {
                requestButton.setAttribute('aria-busy', 'true');
            }

            availabilityRequest = new AbortController();

            try {
                const response = await fetch(`${vehicleBookingForm.dataset.availabilityUrl}?${params}`, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                    signal: availabilityRequest.signal,
                });
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

                const isAvailable = result.data.some(
                    (vehicle) => String(vehicle.id) === vehicleBookingForm.dataset.vehicleId,
                );
                availabilityStatus?.classList.remove('is-loading');
                availabilityStatus?.classList.add(isAvailable ? 'is-available' : 'is-unavailable');

                if (availabilityStatus) {
                    availabilityStatus.textContent = isAvailable
                        ? 'Available for your selected dates. Availability may change until your request is confirmed.'
                        : 'This vehicle is not available for your selected dates. Try different dates or browse the fleet.';
                }

                if (requestButton instanceof HTMLButtonElement) {
                    requestButton.disabled = !isAvailable;
                    requestButton.removeAttribute('aria-busy');
                }
            } catch (error) {
                if (error.name === 'AbortError' || sequence !== availabilitySequence) {
                    return;
                }

                availabilityStatus?.classList.remove('is-loading');
                availabilityStatus?.classList.add('is-error');
                if (availabilityStatus) {
                    availabilityStatus.textContent = error instanceof TypeError
                        ? 'Could not reach the availability service. Check your connection and try again.'
                        : error.message;
                }
                if (requestButton instanceof HTMLButtonElement) {
                    requestButton.disabled = true;
                    requestButton.removeAttribute('aria-busy');
                }
            }
        };
        const scheduleAvailabilityCheck = () => {
            window.clearTimeout(availabilityTimer);
            availabilityRequest?.abort();
            availabilitySequence++;
            availabilityStatus?.classList.remove('is-loading', 'is-error', 'is-available', 'is-unavailable');
            if (requestButton instanceof HTMLButtonElement) {
                requestButton.disabled = true;
                requestButton.removeAttribute('aria-busy');
            }
            availabilityTimer = window.setTimeout(() => {
                void checkVehicleAvailability();
            }, 250);
        };

        [pickupDate, pickupTime, returnDate, returnTime].forEach((field) => {
            const updateBookingState = () => {
                refreshDateAndTimeConstraints();
                scheduleAvailabilityCheck();
            };
            field.addEventListener('change', updateBookingState);
            field.addEventListener('input', updateBookingState);
        });

        vehicleBookingForm.addEventListener('submit', (event) => {
            refreshDateAndTimeConstraints();
            if (!vehicleBookingForm.reportValidity()) {
                event.preventDefault();
                return;
            }

            if (!(requestButton instanceof HTMLButtonElement) || requestButton.disabled) {
                event.preventDefault();
            }
        });

        refreshDateAndTimeConstraints();
        if (pickupDate.value && pickupTime.value && returnDate.value && returnTime.value) {
            scheduleAvailabilityCheck();
        }
    }
}

const fleet = document.querySelector('[data-fleet]');

if (fleet) {
    const cards = [...fleet.querySelectorAll('[data-fleet-card]')];
    const filterButtons = [...fleet.querySelectorAll('[data-filter]')];
    const resultCount = fleet.querySelector('[data-fleet-count]');
    const viewport = fleet.querySelector('[data-fleet-viewport]');
    const track = fleet.querySelector('[data-fleet-track]');
    const progress = fleet.querySelector('[data-fleet-progress]');
    const progressFill = fleet.querySelector('[data-fleet-progress-fill]');
    const progressValue = fleet.querySelector('[data-fleet-progress-value]');
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const dialog = document.querySelector('[data-vehicle-dialog]');
    const dialogPanel = document.querySelector('[data-vehicle-dialog-panel]');
    const dialogImage = document.querySelector('[data-dialog-image]');
    const dialogClose = document.querySelector('[data-dialog-close]');
    const bookingLink = document.querySelector('[data-dialog-booking-link]');
    let activeFilter = 'all';
    let activeDialogTween;
    let activeVehicle;

    const updateCount = () => {
        const visibleCount = cards.filter((card) => !card.hidden).length;
        resultCount.textContent = `${String(visibleCount).padStart(2, '0')} ${visibleCount === 1 ? 'vehicle' : 'vehicles'}`;
    };

    const updateProgress = (value) => {
        const percentage = Math.round(value * 100);

        progressFill.style.transform = `scaleX(${value})`;
        progress.setAttribute('aria-valuenow', String(percentage));
        progressValue.textContent = String(percentage).padStart(2, '0');
    };

    const showFilter = (category) => {
        const currentCards = cards.filter((card) => !card.hidden);
        const applyFilter = () => {
            cards.forEach((card) => {
                const isVisible = category === 'all' || card.dataset.category === category;

                card.hidden = !isVisible;
                card.inert = !isVisible;
                card.setAttribute('aria-hidden', String(!isVisible));
                gsap.set(card, { clearProps: 'opacity,visibility,transform' });
            });

            activeFilter = category;
            updateCount();

            const visibleCards = cards.filter((card) => !card.hidden);

            if (!reduceMotion) {
                gsap.fromTo(
                    visibleCards,
                    { autoAlpha: 0, y: 12 },
                    { autoAlpha: 1, y: 0, duration: 0.32, stagger: 0.045, ease: 'power2.out' },
                );
            }

            ScrollTrigger.refresh();

            const horizontalTrigger = ScrollTrigger.getById('fleet-horizontal');

            if (horizontalTrigger) {
                horizontalTrigger.scroll(horizontalTrigger.start);
                ScrollTrigger.update();
            }
        };

        if (reduceMotion || currentCards.length === 0) {
            applyFilter();
            return;
        }

        gsap.to(currentCards, {
            autoAlpha: 0,
            y: -8,
            duration: 0.16,
            stagger: 0.018,
            ease: 'power1.in',
            onComplete: applyFilter,
        });
    };

    filterButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const category = button.dataset.filter;

            if (category === activeFilter) {
                return;
            }

            filterButtons.forEach((filterButton) => {
                const isActive = filterButton === button;

                filterButton.classList.toggle('is-active', isActive);
                filterButton.setAttribute('aria-pressed', String(isActive));
            });

            showFilter(category);
        });
    });

    cards.forEach((card) => {
        card.inert = false;
        card.setAttribute('aria-hidden', 'false');
    });

    updateCount();
    updateProgress(0);

    const media = gsap.matchMedia();

    media.add('(min-width: 901px) and (prefers-reduced-motion: no-preference)', () => {
        const distance = () => Math.max(0, track.scrollWidth - viewport.clientWidth);

        const horizontalTween = gsap.to(track, {
            x: () => -distance(),
            ease: 'none',
            scrollTrigger: {
                id: 'fleet-horizontal',
                trigger: fleet,
                start: 'top top',
                end: () => `+=${distance()}`,
                pin: true,
                scrub: 0.65,
                anticipatePin: 1,
                invalidateOnRefresh: true,
                onUpdate: (self) => updateProgress(self.progress),
                onRefresh: (self) => updateProgress(self.progress),
            },
        });

        cards.forEach((card) => {
            gsap.fromTo(
                card,
                { y: 18, autoAlpha: 0.72 },
                {
                    y: 0,
                    autoAlpha: 1,
                    ease: 'none',
                    scrollTrigger: {
                        trigger: card,
                        containerAnimation: horizontalTween,
                        start: 'left 96%',
                        end: 'left 60%',
                        scrub: true,
                    },
                },
            );

            const image = card.querySelector('[data-vehicle-image]');

            if (image) {
                gsap.fromTo(
                    image,
                    { scale: 1.055 },
                    {
                        scale: 1,
                        ease: 'none',
                        scrollTrigger: {
                            trigger: card,
                            containerAnimation: horizontalTween,
                            start: 'left 100%',
                            end: 'left 35%',
                            scrub: true,
                        },
                    },
                );
            }
        });

        return () => {
            updateProgress(0);
        };
    });

    if (dialog && dialogPanel && dialogImage) {
        const dialogImagePlaceholder = dialog.querySelector('[data-dialog-image-placeholder]');
        const scheduleStatus = dialog.querySelector('[data-dialog-schedule-status]');
        const scheduleList = dialog.querySelector('[data-dialog-schedule-list]');
        const fillDialog = (card) => {
            const values = {
                '[data-dialog-title]': card.dataset.name,
                '[data-dialog-category]': card.dataset.categoryLabel,
                '[data-dialog-category-value]': card.dataset.categoryLabel,
                '[data-dialog-passengers]': card.dataset.passengers,
                '[data-dialog-transmission]': card.dataset.transmission,
                '[data-dialog-fuel]': card.dataset.fuel,
                '[data-dialog-price]': card.dataset.price,
            };

            Object.entries(values).forEach(([selector, value]) => {
                dialog.querySelector(selector).textContent = value;
            });

            if (card.dataset.image) {
                dialogImage.src = card.dataset.image;
                dialogImage.alt = card.dataset.imageAlt;
                dialogImage.hidden = false;
                dialogImagePlaceholder.hidden = true;
            } else {
                dialogImage.removeAttribute('src');
                dialogImage.alt = '';
                dialogImage.hidden = true;
                dialogImagePlaceholder.hidden = false;
            }
        };

        let scheduleRequest;
        let scheduleSequence = 0;

        const loadSchedule = async (card) => {
            const sequence = ++scheduleSequence;
            scheduleRequest?.abort();
            scheduleRequest = new AbortController();
            scheduleList.replaceChildren();
            scheduleStatus.className = 'vehicle-dialog-schedule-status is-loading';
            scheduleStatus.textContent = 'Loading current availability…';

            try {
                const response = await fetch(card.dataset.scheduleUrl, {
                    headers: { Accept: 'application/json' },
                    cache: 'no-store',
                    signal: scheduleRequest.signal,
                });

                if (!response.ok) {
                    throw new Error(`Schedule request failed with status ${response.status}`);
                }

                const { data } = await response.json();

                if (sequence !== scheduleSequence) {
                    return;
                }

                const statusLabels = {
                    available: 'Available now',
                    reserved: 'Booked now',
                    ongoing: 'Rental in progress',
                    maintenance: 'Under maintenance',
                    inactive: 'Not currently available for rental',
                };
                const statusLabel = statusLabels[data.status];

                if (!statusLabel || !Array.isArray(data.periods)) {
                    throw new Error('The schedule response was incomplete');
                }

                scheduleStatus.className = `vehicle-dialog-schedule-status is-${data.status}`;
                scheduleStatus.textContent = statusLabel;

                if (data.periods.length === 0) {
                    const item = document.createElement('li');
                    item.textContent = `No confirmed bookings or maintenance scheduled in the next ${data.days_ahead} days.`;
                    scheduleList.append(item);
                    return;
                }

                data.periods.forEach((period) => {
                    const item = document.createElement('li');
                    const label = document.createElement('strong');
                    const dates = document.createElement('span');

                    label.textContent = period.label;
                    dates.textContent = `${period.start} – ${period.end}`;
                    item.append(label, dates);
                    scheduleList.append(item);
                });
            } catch (error) {
                if (error.name === 'AbortError' || sequence !== scheduleSequence) {
                    return;
                }

                scheduleStatus.className = 'vehicle-dialog-schedule-status is-error';
                scheduleStatus.textContent = 'We could not load the latest schedule. Continue to booking to check availability.';
            }
        };

        const openDialog = (card) => {
            activeVehicle = card;
            fillDialog(card);
            dialog.showModal();
            loadSchedule(card);
            activeDialogTween?.kill();

            if (reduceMotion) {
                gsap.set(dialogPanel, { clearProps: 'opacity,visibility,transform' });
            } else {
                activeDialogTween = gsap.fromTo(
                    dialogPanel,
                    { autoAlpha: 0, y: 18, scale: 0.985 },
                    { autoAlpha: 1, y: 0, scale: 1, duration: 0.3, ease: 'power2.out' },
                );
            }

            dialogClose.focus();
        };

        cards.forEach((card) => {
            card.querySelectorAll('[data-vehicle-open], [data-vehicle-details]').forEach((button) => {
                button.addEventListener('click', () => openDialog(card));
            });
        });

        dialogClose.addEventListener('click', () => dialog.close());

        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) {
                dialog.close();
            }
        });

        dialog.addEventListener('cancel', (event) => {
            event.preventDefault();
            dialog.close();
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && dialog.open) {
                event.preventDefault();
                dialog.close();
            }
        }, true);

        dialog.addEventListener('close', () => {
            scheduleRequest?.abort();
            activeDialogTween?.kill();
            gsap.set(dialogPanel, { clearProps: 'opacity,visibility,transform' });
        });

        bookingLink.addEventListener('click', (event) => {
            event.preventDefault();
            window.dispatchEvent(new CustomEvent('fleetguard:book-vehicle', {
                detail: {
                    id: activeVehicle?.dataset.vehicleId,
                },
            }));
            dialog.close();
        });
    }
}
