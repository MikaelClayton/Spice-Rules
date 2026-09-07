const chipClassName = 'badge badge-secondary max-w-[10rem] gap-1 pr-1';

function pickerRoot(target) {
    return target.closest('[data-people-picker]');
}

function searchInput(root) {
    return root.querySelector('[data-people-search]');
}

function setOpen(root, open) {
    const search = searchInput(root);

    root.toggleAttribute('data-open', open);

    if (search instanceof HTMLInputElement) {
        search.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
}

function closeAllPickers(except = null) {
    document.querySelectorAll('[data-people-picker]').forEach((root) => {
        if (root instanceof HTMLElement && root !== except) {
            setOpen(root, false);
        }
    });
}

function createChip(id, label) {
    const chip = document.createElement('span');
    chip.className = chipClassName;

    const text = document.createElement('span');
    text.className = 'truncate';
    text.textContent = label;

    const remove = document.createElement('button');
    remove.type = 'button';
    remove.className = 'leading-none text-secondary-content/70 hover:text-secondary-content';
    remove.setAttribute('data-people-chip-remove', id);
    remove.setAttribute('aria-label', `Remove ${label}`);
    remove.textContent = '×';

    chip.append(text, remove);

    return chip;
}

function renderChips(root) {
    const chips = root.querySelector('[data-people-chips]');

    if (! chips) {
        return;
    }

    chips.replaceChildren(
        ...[...root.querySelectorAll('[data-people-option] input[type="checkbox"]:checked')].map((input) => {
            const option = input.closest('[data-people-option]');

            return createChip(input.value, option?.getAttribute('data-label') || '');
        }),
    );
}

function filterPicker(root) {
    const search = searchInput(root);
    const query = (search instanceof HTMLInputElement ? search.value : '').trim().toLowerCase();
    let visible = 0;

    root.querySelectorAll('[data-people-option]').forEach((option) => {
        const match = (option.getAttribute('data-name') || '').includes(query);
        option.hidden = ! match;

        if (match) {
            visible += 1;
        }
    });

    const empty = root.querySelector('[data-people-empty]');

    if (empty instanceof HTMLElement) {
        empty.hidden = visible > 0;
    }
}

function firstVisibleOption(root) {
    return [...root.querySelectorAll('[data-people-option]')].find((option) => ! option.hidden);
}

document.addEventListener('focusin', (event) => {
    const target = event.target;

    if (! (target instanceof Element)) {
        return;
    }

    const root = pickerRoot(target);

    if (root) {
        closeAllPickers(root);
        setOpen(root, true);
    }
});

document.addEventListener('mousedown', (event) => {
    const target = event.target;

    if (target instanceof Element && target.closest('[data-people-list]')) {
        event.preventDefault();
    }
});

document.addEventListener('input', (event) => {
    const target = event.target;

    if (! (target instanceof HTMLInputElement) || ! target.matches('[data-people-search]')) {
        return;
    }

    const root = pickerRoot(target);

    if (root) {
        filterPicker(root);
        setOpen(root, true);
    }
});

document.addEventListener('change', (event) => {
    const target = event.target;

    if (! (target instanceof HTMLInputElement) || ! target.matches('[data-people-option] input[type="checkbox"]')) {
        return;
    }

    const root = pickerRoot(target);

    if (root) {
        renderChips(root);
        setOpen(root, true);
    }
});

document.addEventListener('click', (event) => {
    const target = event.target;

    if (! (target instanceof Element)) {
        return;
    }

    const remove = target.closest('[data-people-chip-remove]');

    if (remove instanceof HTMLButtonElement) {
        const root = pickerRoot(remove);
        const id = remove.getAttribute('data-people-chip-remove');
        const input = root?.querySelector(`[data-people-option] input[value="${id}"]`);

        if (input instanceof HTMLInputElement) {
            event.preventDefault();
            input.checked = false;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }

        return;
    }

    const root = pickerRoot(target);

    if (root) {
        closeAllPickers(root);
        setOpen(root, true);

        if (! target.closest('[data-people-list]') && ! target.matches('[data-people-search]')) {
            const search = searchInput(root);

            if (search instanceof HTMLInputElement) {
                search.focus();
            }
        }

        return;
    }

    closeAllPickers();
});

document.addEventListener('keydown', (event) => {
    const target = event.target;

    if (! (target instanceof HTMLInputElement) || ! target.matches('[data-people-search]')) {
        return;
    }

    const root = pickerRoot(target);

    if (! root) {
        return;
    }

    if (event.key === 'Escape') {
        setOpen(root, false);
        target.blur();

        return;
    }

    if (event.key !== 'Enter') {
        return;
    }

    event.preventDefault();

    const input = firstVisibleOption(root)?.querySelector('input[type="checkbox"]');

    if (input instanceof HTMLInputElement) {
        input.checked = ! input.checked;
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }
});

document.querySelectorAll('[data-people-picker]').forEach((root) => {
    if (root instanceof HTMLElement) {
        renderChips(root);
        filterPicker(root);
    }
});
