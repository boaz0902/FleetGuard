import './bootstrap';
import './fleet.js';
import './booking.js';
import './customer-bookings.js';
import './admin-dashboard.js';
import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

const header = document.querySelector('[data-site-header]');
const menuToggle = document.querySelector('[data-menu-toggle]');
const navigation = document.querySelector('[data-navigation]');
const hero = document.querySelector('[data-hero]');
const bookingForm = document.querySelector('[data-booking-form]');
const pickupDate = document.querySelector('[data-pickup-date]');
const returnDate = document.querySelector('[data-return-date]');
const bookingMessage = document.querySelector('[data-booking-message]');
const publicNavigationLinks = document.querySelectorAll('[data-section-link]');
const searchButton = bookingForm?.querySelector('[data-search-button]');
const searchButtonLabel = searchButton?.querySelector('[data-search-label]');
const searchButtonDefaultLabel = searchButtonLabel?.textContent?.trim() ?? 'Search available cars';
const bookingFields = bookingForm
    ? [...bookingForm.querySelectorAll('[data-booking-field]')]
    : [];
const scrollRevealItems = document.querySelectorAll('[data-scroll-reveal]');
const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

document.querySelectorAll('[data-password-toggle]').forEach((toggle) => {
    const inputId = toggle.getAttribute('aria-controls');
    const input = inputId ? document.getElementById(inputId) : null;
    const icon = toggle.querySelector('.bi');
    const passwordLabel = toggle.getAttribute('data-password-toggle-label');

    if (!(input instanceof HTMLInputElement) || !icon || !passwordLabel) {
        return;
    }

    toggle.addEventListener('click', () => {
        const showPassword = input.type === 'password';

        input.type = showPassword ? 'text' : 'password';
        toggle.setAttribute('aria-pressed', String(showPassword));
        toggle.setAttribute('aria-label', `${showPassword ? 'Hide' : 'Show'} ${passwordLabel}`);
        icon.classList.toggle('bi-eye', !showPassword);
        icon.classList.toggle('bi-eye-slash', showPassword);
    });
});

const getLocalDate = () => {
    const dateParts = new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Asia/Manila',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).formatToParts(new Date());
    const dateValues = Object.fromEntries(dateParts.map(({ type, value }) => [type, value]));

    return `${dateValues.year}-${dateValues.month}-${dateValues.day}`;
};

const updateReturnDateMinimum = () => {
    if (!pickupDate || !returnDate) {
        return;
    }

    const localDate = getLocalDate();
    returnDate.min = pickupDate.value || localDate;
    returnDate.setCustomValidity(
        pickupDate.value && returnDate.value && returnDate.value <= pickupDate.value
            ? 'Return date must be after the pickup date.'
            : '',
    );
};

const updateBookingFieldError = (field, shouldShow) => {
    const error = document.getElementById(`${field.id}-error`);
    const invalid = shouldShow && !field.validity.valid;
    const label = bookingForm?.querySelector(`label[for="${field.id}"]`)?.textContent.trim();

    if (invalid) {
        field.setAttribute('aria-invalid', 'true');
        field.closest('.field-control')?.classList.add('is-invalid');

        if (error) {
            error.textContent = field.validity.valueMissing
                ? `${label} is required.`
                : field.validationMessage;
        }

        return;
    }

    field.removeAttribute('aria-invalid');
    field.closest('.field-control')?.classList.remove('is-invalid');

    if (error) {
        error.textContent = '';
    }
};

if (publicNavigationLinks.length > 0) {
    const sectionLinks = [...publicNavigationLinks]
        .map((link) => {
            const target = new URL(link.href, window.location.href);

            return {
                link,
                section: target.origin === window.location.origin
                    ? document.getElementById(target.hash.slice(1))
                    : null,
            };
        })
        .filter(({ section }) => section);
    const sections = [...new Set(sectionLinks.map(({ section }) => section))]
        .sort((first, second) => first.compareDocumentPosition(second) & Node.DOCUMENT_POSITION_FOLLOWING ? -1 : 1);
    let scrollSpyPending = false;

    const updateCurrentNavigationLink = () => {
        const marker = window.scrollY + Math.min(window.innerHeight * 0.42, 320);
        const currentSection = sections
            .filter((section) => section.getBoundingClientRect().top + window.scrollY <= marker)
            .at(-1);
        const currentLink = sectionLinks.find(({ section }) => section === currentSection)?.link;

        publicNavigationLinks.forEach((link) => link.removeAttribute('aria-current'));
        currentLink?.setAttribute('aria-current', 'location');
    };

    const scheduleNavigationUpdate = () => {
        if (scrollSpyPending) {
            return;
        }

        scrollSpyPending = true;
        window.requestAnimationFrame(() => {
            updateCurrentNavigationLink();
            scrollSpyPending = false;
        });
    };

    updateCurrentNavigationLink();
    window.addEventListener('scroll', scheduleNavigationUpdate, { passive: true });
    window.addEventListener('hashchange', updateCurrentNavigationLink);
}

if (header) {
    const updateHeader = () => {
        header.classList.toggle('is-scrolled', window.scrollY > 24);
    };

    updateHeader();
    window.addEventListener('scroll', updateHeader, { passive: true });
}

if (scrollRevealItems.length > 0 && !reduceMotion) {
    scrollRevealItems.forEach((item) => {
        gsap.fromTo(item, {
            autoAlpha: 0,
            y: 18,
        }, {
            autoAlpha: 1,
            y: 0,
            duration: 0.65,
            ease: 'power2.out',
            immediateRender: false,
            scrollTrigger: {
                trigger: item,
                start: 'top 88%',
                once: true,
            },
        });
    });
}

const accountMenu = document.querySelector('[data-account-menu]');
const accountToggle = accountMenu?.querySelector('[data-account-toggle]');
const accountPanel = accountMenu?.querySelector('[data-account-panel]');

if (accountMenu && accountToggle && accountPanel) {
    const closeAccountMenu = (restoreFocus = false) => {
        accountPanel.hidden = true;
        accountToggle.setAttribute('aria-expanded', 'false');
        accountToggle.setAttribute('aria-label', 'Open account menu');

        if (restoreFocus) {
            accountToggle.focus();
        }
    };

    accountToggle.addEventListener('click', () => {
        const isExpanded = accountToggle.getAttribute('aria-expanded') === 'true';
        accountPanel.hidden = isExpanded;
        accountToggle.setAttribute('aria-expanded', String(!isExpanded));
        accountToggle.setAttribute('aria-label', isExpanded ? 'Open account menu' : 'Close account menu');
    });

    document.addEventListener('click', (event) => {
        if (event.target instanceof Node && !accountMenu.contains(event.target)) {
            closeAccountMenu();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && accountToggle.getAttribute('aria-expanded') === 'true') {
            event.preventDefault();
            closeAccountMenu(true);
        }
    });
}

if (menuToggle && header && navigation) {
    const mobileNavigation = window.matchMedia('(max-width: 760px)');

    const focusableNavigationItems = () => [
        menuToggle,
        ...navigation.querySelectorAll('a[href], button:not(:disabled)'),
    ].filter((item) => !item.closest('[hidden]') && item.getClientRects().length > 0);

    const syncNavigationState = () => {
        const isMobile = mobileNavigation.matches;
        const isExpanded = isMobile && header.classList.contains('menu-open');

        menuToggle.setAttribute('aria-expanded', String(isExpanded));
        menuToggle.setAttribute('aria-label', isExpanded ? 'Close navigation menu' : 'Open navigation menu');
        navigation.inert = isMobile && !isExpanded;
        navigation.setAttribute('aria-hidden', String(isMobile && !isExpanded));
        document.documentElement.classList.toggle('navigation-locked', isExpanded);
        document.body.classList.toggle('navigation-locked', isExpanded);
    };

    const closeMenu = (restoreFocus = false) => {
        header.classList.remove('menu-open');
        syncNavigationState();

        if (restoreFocus) {
            menuToggle.focus();
        }
    };

    syncNavigationState();

    menuToggle.addEventListener('click', () => {
        const shouldOpen = menuToggle.getAttribute('aria-expanded') !== 'true';
        header.classList.toggle('menu-open', shouldOpen);
        syncNavigationState();

        if (shouldOpen) {
            window.requestAnimationFrame(() => navigation.querySelector('a[href]')?.focus());
        }
    });

    navigation.addEventListener('click', (event) => {
        if (
            mobileNavigation.matches
            && event.target instanceof Element
            && event.target.closest('a')
        ) {
            closeMenu();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (!mobileNavigation.matches || !header.classList.contains('menu-open')) {
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            closeMenu(true);
            return;
        }

        if (event.key === 'Tab') {
            const focusableItems = focusableNavigationItems();
            const first = focusableItems[0];
            const last = focusableItems.at(-1);

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last?.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first?.focus();
            }
        }
    });

    mobileNavigation.addEventListener('change', () => {
        if (!mobileNavigation.matches) {
            header.classList.remove('menu-open');
        }

        syncNavigationState();
    });
}

if (pickupDate && returnDate) {
    pickupDate.min = getLocalDate();
    updateReturnDateMinimum();
}

if (bookingForm) {
    bookingFields.forEach((field) => {
        const syncField = () => {
            if (field === pickupDate || field === returnDate) {
                updateReturnDateMinimum();
            }

            bookingFields.forEach((bookingField) => {
                updateBookingFieldError(
                    bookingField,
                    bookingForm.dataset.validationShown === 'true',
                );
            });
        };

        field.addEventListener('input', syncField);
        field.addEventListener('change', syncField);
    });
}

if (bookingForm && bookingMessage) {
    bookingForm.addEventListener('submit', (event) => {
        event.preventDefault();
        bookingMessage.textContent = '';
        updateReturnDateMinimum();

        bookingForm.dataset.validationShown = 'true';
        bookingFields.forEach((field) => updateBookingFieldError(field, true));

        if (!bookingForm.reportValidity()) {
            return;
        }

        bookingForm.dataset.validationShown = 'false';
        bookingMessage.textContent = 'Checking live availability for the selected dates…';
        if (searchButton instanceof HTMLButtonElement) {
            searchButton.disabled = true;
            searchButton.setAttribute('aria-busy', 'true');
            if (searchButtonLabel) {
                searchButtonLabel.textContent = 'Checking availability…';
            }
        }

        window.dispatchEvent(new CustomEvent('fleetguard:booking-start', {
            detail: {
                pickupLocation: bookingForm.elements.pickup_location.value,
                pickupDate: bookingForm.elements.pickup_date.value,
                returnDate: bookingForm.elements.return_date.value,
                vehicleCategory: bookingForm.elements.vehicle_category.value,
            },
        }));
    });

    bookingForm.addEventListener('input', () => {
        bookingMessage.textContent = '';
    });

    window.addEventListener('fleetguard:availability-search-settled', () => {
        if (searchButton instanceof HTMLButtonElement) {
            searchButton.disabled = false;
            searchButton.removeAttribute('aria-busy');
            if (searchButtonLabel) {
                searchButtonLabel.textContent = searchButtonDefaultLabel;
            }
        }
    });
}

if (hero && !reduceMotion) {
    const heroImage = hero.querySelector('[data-hero-image]');
    const headlineLines = hero.querySelectorAll('[data-headline-line]');
    const revealItems = hero.querySelectorAll('[data-reveal]');
    const bookingPanel = hero.querySelector('[data-booking-panel]');
    const heroRail = hero.querySelector('[data-hero-rail]');
    const scrollCue = hero.querySelector('.scroll-cue');
    const intro = gsap.timeline({
        defaults: { ease: 'power3.out' },
        onComplete: () => heroImage?.style.removeProperty('will-change'),
    });

    if (heroImage) {
        heroImage.style.willChange = 'transform';
        intro.fromTo(
            heroImage,
            { scale: 1.055 },
            { scale: 1, duration: 2.3, ease: 'power2.out' },
            0,
        );
    }

    intro
        .from(header, { y: -18, autoAlpha: 0, duration: 0.7 }, 0)
        .from(revealItems[0], { y: 14, autoAlpha: 0, duration: 0.6 }, 0.22)
        .from(headlineLines, { yPercent: 105, duration: 0.85, stagger: 0.12 }, 0.36)
        .from(revealItems[1], { y: 12, autoAlpha: 0, duration: 0.6 }, 1.08)
        .from(revealItems[2], { y: 12, autoAlpha: 0, duration: 0.55 }, 1.32)
        .from(heroRail, { x: 8, autoAlpha: 0, duration: 0.55 }, 0.54)
        .from(scrollCue, { y: 10, autoAlpha: 0, duration: 0.45 }, 1.62)
        .from(bookingPanel, { y: 24, autoAlpha: 0, duration: 0.7 }, 1.92);

    if (heroImage) {
        const media = gsap.matchMedia();

        media.add(
            '(min-width: 901px) and (hover: hover) and (pointer: fine) and (prefers-reduced-motion: no-preference)',
            () => {
                const parallax = gsap.to(heroImage, {
                    yPercent: 5,
                    ease: 'none',
                    scrollTrigger: {
                        trigger: hero,
                        start: 'top top',
                        end: 'bottom top',
                        scrub: 0.6,
                    },
                });

                return () => {
                    parallax.scrollTrigger?.kill();
                    parallax.kill();
                };
            },
        );
    }
}

const syncPageVisibility = () => {
    document.documentElement.classList.toggle('page-hidden', document.hidden);
};

syncPageVisibility();
document.addEventListener('visibilitychange', syncPageVisibility);

const refreshScrollTriggers = () => ScrollTrigger.refresh();
window.addEventListener('load', refreshScrollTriggers, { once: true });
document.fonts?.ready.then(refreshScrollTriggers);
document.querySelector('[data-hero-image]')?.addEventListener('load', refreshScrollTriggers, { once: true });
