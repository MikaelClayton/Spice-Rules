export function setButtonLoading(button, isLoading) {
    if (!(button instanceof HTMLButtonElement) && !(button instanceof HTMLInputElement)) {
        return;
    }

    button.disabled = isLoading;
    button.setAttribute('aria-busy', isLoading ? 'true' : 'false');

    if (!(button instanceof HTMLButtonElement)) {
        return;
    }

    const spinner = button.querySelector('[data-btn-spinner]');

    if (isLoading) {
        if (spinner) {
            return;
        }

        const next = document.createElement('span');
        next.className = button.classList.contains('btn-sm') || button.classList.contains('btn-xs')
            ? 'loading loading-spinner loading-xs'
            : 'loading loading-spinner';
        next.setAttribute('data-btn-spinner', '');
        next.setAttribute('aria-hidden', 'true');
        button.prepend(next);

        return;
    }

    spinner?.remove();
}

document.addEventListener('submit', (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    if (form.getAttribute('method') === 'dialog' || form.hasAttribute('data-share-form')) {
        return;
    }

    if (form.dataset.submitting === 'true') {
        event.preventDefault();

        return;
    }

    form.dataset.submitting = 'true';

    const button = submitButton(event, form);

    window.setTimeout(() => {
        setButtonLoading(button, true);
    }, 0);
});

function submitButton(event, form) {
    if (event.submitter instanceof HTMLButtonElement || event.submitter instanceof HTMLInputElement) {
        return event.submitter;
    }

    return form.querySelector('button[type="submit"], input[type="submit"]');
}
