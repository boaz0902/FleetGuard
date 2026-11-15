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
const publicNavigationLinks = document.querySelectorAll('[data-public-nav-link]');
const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

if (publicNavigationLinks.length > 0) {
    const updateCurrentNavigationLink = () => {
        publicNavigationLinks.forEach((link) => {
            if (link.getAttribute('href') === window.location.hash) {
                link.setAttribute('aria-current', 'location');
            } else {
                link.removeAttribute('aria-current');
            }
        });
    };

    updateCurrentNavigationLink();
    window.addEventListener('hashchange', updateCurrentNavigationLink);
}

if (header) {
    const updateHeader = () => {
        header.classList.toggle('is-scrolled', window.scrollY > 24);
    };

    updateHeader();
    window.addEventListener('scroll', updateHeader, { passive: true });
}

if (menuToggle && header && navigation) {
    const mobileNavigation = window.matchMedia('(max-width: 760px)');

    const syncNavigationState = () => {
        const isMobile = mobileNavigation.matches;
        const isExpanded = isMobile && header.classList.contains('menu-open');

        menuToggle.setAttribute('aria-expanded', String(isExpanded));
        menuToggle.setAttribute('aria-label', isExpanded ? 'Close navigation menu' : 'Open navigation menu');
        navigation.inert = isMobile && !isExpanded;
        navigation.setAttribute('aria-hidden', String(isMobile && !isExpanded));
    };

    const closeMenu = () => {
        header.classList.remove('menu-open');
        syncNavigationState();
    };

    syncNavigationState();

    menuToggle.addEventListener('click', () => {
        header.classList.toggle('menu-open', menuToggle.getAttribute('aria-expanded') !== 'true');
        syncNavigationState();
    });

    navigation.addEventListener('click', (event) => {
        if (event.target instanceof Element && event.target.closest('a')) {
            closeMenu();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeMenu();
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
    const dateParts = new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Asia/Manila',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).formatToParts(new Date());
    const dateValues = Object.fromEntries(dateParts.map(({ type, value }) => [type, value]));
    const localDate = `${dateValues.year}-${dateValues.month}-${dateValues.day}`;

    pickupDate.min = localDate;
    returnDate.min = localDate;

    const updateReturnDateMinimum = () => {
        returnDate.min = pickupDate.value || localDate;
        returnDate.setCustomValidity(
            returnDate.value && returnDate.value <= pickupDate.value
                ? 'Return date must be after the pickup date.'
                : '',
        );
    };

    pickupDate.addEventListener('change', updateReturnDateMinimum);
    returnDate.addEventListener('change', updateReturnDateMinimum);
}

if (bookingForm && bookingMessage) {
    bookingForm.addEventListener('submit', (event) => {
        event.preventDefault();
        bookingMessage.textContent = '';

        if (!bookingForm.reportValidity()) {
            return;
        }

        bookingMessage.textContent = 'Checking live availability for the selected dates…';
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
}

if (hero && !reduceMotion) {
    const heroImage = hero.querySelector('[data-hero-image]');
    const headlineLines = hero.querySelectorAll('[data-headline-line]');
    const revealItems = hero.querySelectorAll('[data-reveal]');
    const bookingPanel = hero.querySelector('[data-booking-panel]');
    const intro = gsap.timeline({ defaults: { ease: 'power3.out' } });

    intro
        .from(header, { y: -18, autoAlpha: 0, duration: 0.7 })
        .from(revealItems[0], { y: 14, autoAlpha: 0, duration: 0.6 }, '-=0.25')
        .from(headlineLines, { yPercent: 105, duration: 0.85, stagger: 0.12 }, '-=0.2')
        .from(revealItems[1], { y: 12, autoAlpha: 0, duration: 0.6 }, '-=0.28')
        .from(revealItems[2], { y: 12, autoAlpha: 0, duration: 0.55 }, '-=0.25')
        .from(bookingPanel, { y: 24, autoAlpha: 0, duration: 0.7 }, '-=0.12');

    gsap.fromTo(
        heroImage,
        { scale: 1.05 },
        { scale: 1, duration: 2.2, ease: 'power2.out' },
    );

    gsap.to(heroImage, {
        yPercent: 8,
        ease: 'none',
        scrollTrigger: {
            trigger: hero,
            start: 'top top',
            end: 'bottom top',
            scrub: 0.6,
        },
    });
}
