document.querySelectorAll('[data-testimonial-review-input]').forEach((input) => {
    const counter = document.querySelector('[data-testimonial-review-count]');

    if (!(input instanceof HTMLTextAreaElement) || !counter) {
        return;
    }

    const updateCount = () => {
        counter.textContent = String(input.value.length);
    };

    input.addEventListener('input', updateCount);
    updateCount();
});
