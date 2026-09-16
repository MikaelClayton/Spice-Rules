const root = document.querySelector('[data-fit-ish-board]');

if (root instanceof HTMLElement) {
    bindFitIshBoard(root);
}

function bindFitIshBoard(board) {
    const cache = new Map();
    const dayUrl = board.getAttribute('data-session-day-url') || '';
    const loadState = { controller: null };

    board.querySelectorAll('[data-tab]').forEach((tab) => {
        tab.addEventListener('change', () => {
            syncTabUrl(tab);
            maybeLoadSessions(board, cache, dayUrl, loadState);
        });
    });

    board.querySelector('[data-session-dates]')?.addEventListener('click', (event) => {
        const target = event.target;
        const button = target instanceof Element ? target.closest('[data-session-date]') : null;

        if (!(button instanceof HTMLElement)) {
            return;
        }

        const date = button.getAttribute('data-session-date');

        if (!date) {
            return;
        }

        loadSessionDay(board, cache, dayUrl, date, loadState);
    });

    maybeLoadSessions(board, cache, dayUrl, loadState);
}

function syncTabUrl(tab) {
    const url = new URL(window.location.href);
    const name = tab.getAttribute('data-tab');

    if (name === 'today') {
        url.searchParams.delete('tab');
        url.searchParams.delete('week');
        url.searchParams.delete('date');
    } else {
        url.searchParams.set('tab', name);

        if (name !== 'weekly') {
            url.searchParams.delete('week');
        }

        if (name !== 'sessions') {
            url.searchParams.delete('date');
        }
    }

    window.history.replaceState({}, '', url);
}

function maybeLoadSessions(board, cache, dayUrl, loadState) {
    const tab = board.querySelector('[data-tab="sessions"]');

    if (!(tab instanceof HTMLInputElement) || !tab.checked) {
        return;
    }

    const date = selectedDate(board);

    if (date) {
        loadSessionDay(board, cache, dayUrl, date, loadState);
    }
}

function selectedDate(board) {
    const urlDate = new URL(window.location.href).searchParams.get('date');
    const active = board.querySelector('[data-session-date][aria-pressed="true"]');
    const first = board.querySelector('[data-session-date]');

    return urlDate || active?.getAttribute('data-session-date') || first?.getAttribute('data-session-date');
}

async function loadSessionDay(board, cache, dayUrl, date, loadState) {
    const region = board.querySelector('[data-session-day]');

    if (!(region instanceof HTMLElement) || !dayUrl.includes('__DATE__')) {
        return;
    }

    markDate(board, date);

    const url = new URL(window.location.href);
    url.searchParams.set('tab', 'sessions');
    url.searchParams.set('date', date);
    window.history.replaceState({}, '', url);

    if (region.getAttribute('data-loaded-date') === date && region.innerHTML.trim() !== '') {
        cache.set(date, region.innerHTML);

        return;
    }

    if (cache.has(date)) {
        region.innerHTML = cache.get(date);
        region.setAttribute('data-loaded-date', date);

        return;
    }

    loadState.controller?.abort();
    const controller = new AbortController();
    loadState.controller = controller;

    region.setAttribute('data-loaded-date', '');
    region.innerHTML = '<div class="card bg-base-100 shadow-xl"><div class="card-body"><p class="text-base-content/70">Loading classes…</p></div></div>';

    try {
        const response = await fetch(dayUrl.replace('__DATE__', encodeURIComponent(date)), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            signal: controller.signal,
        });

        if (!response.ok) {
            throw new Error('Failed to load');
        }

        const data = await response.json();
        const html = typeof data?.html === 'string' ? data.html : '';

        if (loadState.controller !== controller) {
            return;
        }

        cache.set(date, html);
        region.innerHTML = html;
        region.setAttribute('data-loaded-date', date);
    } catch {
        if (controller.signal.aborted) {
            return;
        }

        region.innerHTML = '<div class="card bg-base-100 shadow-xl"><div class="card-body"><p class="text-base-content/70">Couldn\'t load that day.</p></div></div>';
    }
}

function markDate(board, date) {
    board.querySelectorAll('[data-session-date]').forEach((button) => {
        const active = button.getAttribute('data-session-date') === date;
        button.classList.toggle('btn-primary', active);
        button.classList.toggle('btn-ghost', !active);
        button.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
}
