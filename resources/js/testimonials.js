const track = document.querySelector('[data-testimonial-track]');
const cards = [...document.querySelectorAll('.testimonial-card')];
const dialog = document.querySelector('[data-testimonial-dialog]');
const dialogAuthor = dialog?.querySelector('[data-testimonial-dialog-author]');
const dialogContent = dialog?.querySelector('[data-testimonial-dialog-content]');

const updateReadMore = (card) => {
    const review = card.querySelector('[data-testimonial-review]');
    const button = card.querySelector('[data-testimonial-read-more]');
    if (!review || !button) {
        return;
    }

    review.classList.remove('is-truncated');
    const lineHeight = Number.parseFloat(window.getComputedStyle(review).lineHeight);
    const shouldTruncate = lineHeight > 0 && review.scrollHeight > (lineHeight * 4) + 1;
    const isPreview = card.hasAttribute('data-testimonial-preview-card');
    const canOpenDialog = typeof dialog?.showModal === 'function';
    button.hidden = !shouldTruncate || (!isPreview && !canOpenDialog);

    if (!shouldTruncate) {
        review.classList.remove('is-expanded');
        if (isPreview) {
            button.setAttribute('aria-expanded', 'false');
        }
        button.textContent = 'Read more';
    } else if (!isPreview || button.getAttribute('aria-expanded') !== 'true') {
        review.classList.add('is-truncated');
    }
};

cards.forEach((card) => {
    const button = card.querySelector('[data-testimonial-read-more]');
    button?.addEventListener('click', () => {
        if (card.hasAttribute('data-testimonial-preview-card')) {
            const expanded = button.getAttribute('aria-expanded') === 'true';
            const review = card.querySelector('[data-testimonial-review]');
            button.setAttribute('aria-expanded', String(!expanded));
            button.textContent = expanded ? 'Read more' : 'Show less';
            review?.classList.toggle('is-expanded', !expanded);
            review?.classList.toggle('is-truncated', expanded);
            return;
        }

        if (dialog && dialogAuthor && dialogContent && typeof dialog.showModal === 'function') {
            dialogAuthor.textContent = button.dataset.testimonialFullAuthor || '';
            dialogContent.textContent = button.dataset.testimonialFullReview || '';
            dialog.showModal();
        }
    });

    card.addEventListener('testimonial-preview-updated', () => updateReadMore(card));
    updateReadMore(card);
});

window.addEventListener('resize', () => cards.forEach(updateReadMore));

if (track) {
    const previous = document.querySelector('[data-testimonial-prev]');
    const next = document.querySelector('[data-testimonial-next]');
    const progress = document.querySelector('[data-testimonial-progress]');
    const progressFill = progress?.querySelector('span');
    const trackCards = [...track.querySelectorAll('.testimonial-card')];
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const updateControls = () => {
        const maxScroll = Math.max(0, track.scrollWidth - track.clientWidth);
        const progressValue = maxScroll <= 1
            ? 100
            : Math.round((track.scrollLeft / maxScroll) * 100);

        if (previous) {
            previous.disabled = track.scrollLeft <= 1;
        }
        if (next) {
            next.disabled = track.scrollLeft >= maxScroll - 1;
        }
        if (progress && progressFill) {
            progress.setAttribute('aria-valuenow', String(progressValue));
            progressFill.style.width = `${progressValue}%`;
        }
    };

    const scrollTrack = (direction) => {
        const card = trackCards[0];
        if (!card) {
            return;
        }

        const gap = Number.parseFloat(window.getComputedStyle(track).columnGap) || 0;
        track.scrollBy({
            left: direction * (card.getBoundingClientRect().width + gap),
            behavior: reduceMotion ? 'auto' : 'smooth',
        });
    };

    previous?.addEventListener('click', () => scrollTrack(-1));
    next?.addEventListener('click', () => scrollTrack(1));
    track.addEventListener('scroll', updateControls, { passive: true });
    window.addEventListener('resize', updateControls);
    updateControls();
}
