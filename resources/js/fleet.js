import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

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

            gsap.fromTo(
                card.querySelector('[data-vehicle-image]'),
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
        });

        return () => {
            updateProgress(0);
        };
    });

    if (dialog && dialogPanel && dialogImage) {
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

            dialogImage.src = card.dataset.image;
            dialogImage.alt = card.dataset.imageAlt;
        };

        const openDialog = (card) => {
            activeVehicle = card;
            fillDialog(card);
            dialog.showModal();
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
