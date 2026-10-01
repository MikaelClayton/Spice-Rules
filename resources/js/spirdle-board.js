const board = document.querySelector('[data-spirdle-board]');

if (board instanceof HTMLElement) {
    bindSpirdleBoard(board);
}

function bindSpirdleBoard(boardEl) {
    boardEl.querySelectorAll('[data-tab]').forEach((tab) => {
        tab.addEventListener('change', () => {
            const url = new URL(window.location.href);
            const name = tab.getAttribute('data-tab');

            if (name === 'today') {
                url.searchParams.delete('tab');
                url.searchParams.delete('week');
                url.searchParams.delete('challenge');
            } else {
                url.searchParams.set('tab', name);

                if (name !== 'weekly') {
                    url.searchParams.delete('week');
                }

                if (name !== 'challenges') {
                    url.searchParams.delete('challenge');
                }
            }

            window.history.replaceState({}, '', url);
            maybeLoadPracticeResults(boardEl);
        });
    });

    boardEl.addEventListener('click', (event) => {
        const button = event.target instanceof Element ? event.target.closest('[data-reward]') : null;

        if (!(button instanceof HTMLElement)) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        notifyReward(boardEl, button.getAttribute('data-reward') || '');
    });

    maybeLoadPracticeResults(boardEl);
}

function maybeLoadPracticeResults(boardEl) {
    const tab = boardEl.querySelector('[data-tab="you"]');
    const region = boardEl.querySelector('[data-practice-results]');

    if (!(tab instanceof HTMLInputElement) || !tab.checked || !(region instanceof HTMLElement)) {
        return;
    }

    loadPracticeResults(region);
}

async function loadPracticeResults(region) {
    if (region.getAttribute('data-loaded') === 'true' && region.innerHTML.trim() !== '') {
        return;
    }

    const url = region.getAttribute('data-practice-results-url');

    if (!url) {
        return;
    }

    region.setAttribute('data-loaded', '');
    region.innerHTML =
        '<div class="card bg-base-100 shadow-xl"><div class="card-body"><p class="text-base-content/70">Loading practice grids…</p></div></div>';

    try {
        const response = await fetch(url, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error('Failed to load');
        }

        const data = await response.json();
        const html = typeof data?.html === 'string' ? data.html : '';

        region.innerHTML = html;
        region.setAttribute('data-loaded', 'true');
    } catch {
        region.innerHTML =
            '<div class="card bg-base-100 shadow-xl"><div class="card-body"><p class="text-base-content/70">Couldn\'t load practice grids.</p></div></div>';
    }
}

function notifyReward(root, message) {
    const host = root.querySelector('[data-reward-toast]');

    if (!host || message === '') {
        return;
    }

    host.replaceChildren();
    const alert = document.createElement('div');
    alert.setAttribute('role', 'alert');
    alert.className = 'alert shadow-lg max-w-sm';
    const text = document.createElement('span');
    text.textContent = message;
    alert.append(text);
    host.append(alert);
    window.setTimeout(() => alert.remove(), 4000);
}
