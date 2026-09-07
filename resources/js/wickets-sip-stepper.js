function readCount(input) {
    return Number(input.value) || 0;
}

function setCount(root, value) {
    const max = Number(root.getAttribute('data-max')) || 7;
    const countInput = root.querySelector('[data-sip-count]');
    const decrease = root.querySelector('[data-sip-dec]');
    const increase = root.querySelector('[data-sip-inc]');
    const label = root.querySelector('[data-sip-label]');
    const form = root.closest('form');

    if (! (countInput instanceof HTMLInputElement)) {
        return;
    }

    const next = Math.min(max, Math.max(0, Math.round(value)));
    countInput.value = String(next);
    root.style.setProperty('--sip-fill', String(next / max));
    root.toggleAttribute('data-has-sips', next > 0);

    if (label) {
        label.textContent = next === 1 ? '1 sip' : `${next} sips`;
    }

    if (decrease instanceof HTMLButtonElement) {
        decrease.disabled = next <= 0;
    }

    if (increase instanceof HTMLButtonElement) {
        increase.disabled = next >= max;
    }

    if (next > 0 && form) {
        form.querySelectorAll('[data-sip-special]').forEach((input) => {
            if (input instanceof HTMLInputElement) {
                input.checked = false;
            }
        });
    }
}

document.addEventListener('click', (event) => {
    const target = event.target;

    if (! (target instanceof Element)) {
        return;
    }

    const increase = target.closest('[data-sip-inc]');
    const decrease = target.closest('[data-sip-dec]');
    const button = increase ?? decrease;

    if (! (button instanceof HTMLButtonElement)) {
        return;
    }

    const root = button.closest('[data-sip-stepper]');
    const countInput = root?.querySelector('[data-sip-count]');

    if (! (root instanceof HTMLElement) || ! (countInput instanceof HTMLInputElement)) {
        return;
    }

    event.preventDefault();
    setCount(root, readCount(countInput) + (increase ? 1 : -1));
});

document.addEventListener('change', (event) => {
    const target = event.target;

    if (! (target instanceof HTMLInputElement) || ! target.matches('[data-sip-special]') || ! target.checked) {
        return;
    }

    const form = target.closest('form');
    const root = form?.querySelector('[data-sip-stepper]');

    if (root instanceof HTMLElement) {
        setCount(root, 0);
    }
});

document.addEventListener('input', (event) => {
    const target = event.target;

    if (! (target instanceof HTMLInputElement) || ! target.matches('[data-sip-count]')) {
        return;
    }

    const root = target.closest('[data-sip-stepper]');

    if (root instanceof HTMLElement) {
        setCount(root, readCount(target));
    }
});

document.querySelectorAll('[data-sip-stepper]').forEach((root) => {
    const countInput = root.querySelector('[data-sip-count]');

    if (root instanceof HTMLElement && countInput instanceof HTMLInputElement) {
        setCount(root, readCount(countInput));
    }
});
