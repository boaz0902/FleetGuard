document.querySelectorAll('[data-testimonial-form]').forEach((form) => {
    const preview = document.querySelector('[data-testimonial-preview-card]');
    if (!preview) {
        return;
    }

    const nameInput = form.querySelector('[data-preview-name-input]');
    const contentInput = form.querySelector('[data-preview-content-input]');
    const ratingInput = form.querySelector('[data-preview-rating-input]');
    const dateInput = form.querySelector('[data-preview-date-input]');
    const imageInput = form.querySelector('[data-preview-image-input]');
    const removeImageInput = form.querySelector('[data-preview-remove-image]');
    const author = preview.querySelector('[data-testimonial-author]');
    const content = preview.querySelector('[data-testimonial-review]');
    const rating = preview.querySelector('[data-testimonial-rating]');
    const date = preview.querySelector('[data-testimonial-date]');
    const image = preview.querySelector('[data-testimonial-avatar-image]');
    const initials = preview.querySelector('[data-testimonial-avatar-initials]');

    const updateInitials = (value) => value.trim().split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part[0].toLocaleUpperCase()).join('') || '—';

    const updatePreview = () => {
        const name = nameInput?.value.trim() || '';
        if (author) {
            author.textContent = name || 'Author name';
        }
        if (initials) {
            initials.textContent = updateInitials(name);
        }
        if (content) {
            content.textContent = contentInput?.value || '';
        }
        preview.dispatchEvent(new Event('testimonial-preview-updated'));

        const selectedRating = Number(ratingInput?.value);
        if (rating) {
            rating.hidden = selectedRating < 1 || selectedRating > 5;
            rating.setAttribute('aria-label', selectedRating >= 1 && selectedRating <= 5
                ? `Rated ${selectedRating} out of 5`
                : 'Rating preview');
            rating.replaceChildren(...Array.from({ length: 5 }, (_, index) => {
                const star = document.createElement('i');
                star.className = `bi ${index < selectedRating ? 'bi-star-fill' : 'bi-star'}`;
                star.setAttribute('aria-hidden', 'true');
                return star;
            }));
        }

        const reviewDate = dateInput?.value;
        if (date) {
            date.dateTime = reviewDate || '';
            date.textContent = reviewDate
                ? new Intl.DateTimeFormat('en-PH', { dateStyle: 'medium', timeZone: 'Asia/Manila' }).format(new Date(`${reviewDate}T12:00:00`))
                : 'Review date';
        }
    };

    const showInitials = () => {
        if (image) {
            image.hidden = true;
            image.removeAttribute('src');
        }
        if (initials) {
            initials.hidden = false;
        }
    };

    nameInput?.addEventListener('input', updatePreview);
    contentInput?.addEventListener('input', updatePreview);
    ratingInput?.addEventListener('change', updatePreview);
    dateInput?.addEventListener('change', updatePreview);
    imageInput?.addEventListener('change', () => {
        const file = imageInput.files?.[0];
        if (file && image) {
            image.src = URL.createObjectURL(file);
            image.hidden = false;
            if (initials) {
                initials.hidden = true;
            }
            if (removeImageInput) {
                removeImageInput.checked = false;
            }
        }
    });
    removeImageInput?.addEventListener('change', () => {
        if (removeImageInput.checked) {
            showInitials();
        }
    });

    if (removeImageInput?.checked) {
        showInitials();
    }

    updatePreview();
});
