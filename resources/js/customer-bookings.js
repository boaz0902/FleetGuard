const cancelDialog = document.querySelector('[data-cancel-dialog]');

if (cancelDialog instanceof HTMLDialogElement) {
    const reason = cancelDialog.querySelector('[data-cancel-reason]');
    const reasonCount = cancelDialog.querySelector('[data-cancel-reason-count]');
    const cancelForm = cancelDialog.querySelector('[data-cancel-form]');
    const cancelSubmit = cancelDialog.querySelector('[data-cancel-submit]');
    const cancelTrigger = document.querySelector('[data-cancel-dialog-open]');
    const closeButtons = cancelDialog.querySelectorAll('[data-cancel-dialog-close]');

    cancelTrigger?.addEventListener('click', () => cancelDialog.showModal());

    closeButtons.forEach((button) => {
        button.addEventListener('click', () => cancelDialog.close());
    });

    cancelDialog.addEventListener('click', (event) => {
        if (event.target === cancelDialog) {
            cancelDialog.close();
        }
    });

    reason?.addEventListener('input', () => {
        if (reasonCount) {
            reasonCount.textContent = String(reason.value.length);
        }
    });

    cancelForm?.addEventListener('submit', () => {
        if (cancelSubmit instanceof HTMLButtonElement) {
            cancelSubmit.disabled = true;
            cancelSubmit.setAttribute('aria-busy', 'true');
            cancelSubmit.textContent = 'Cancelling request…';
        }
    });
}

document.querySelectorAll('[data-booking-vehicle-image]').forEach((image) => {
    image.addEventListener('error', () => {
        image.hidden = true;
    }, { once: true });
});
