const root = document.querySelector('[data-profile-challenges]');

if (root) {
    const state = {
        player: 'all',
        page: 1,
        hasMore: root.getAttribute('data-challenge-has-more') === 'true',
        loading: false,
        shareChallengeId: null,
        shareDate: null,
    };

    bindFilters(root, state);
    bindLoadMore(root, state);
    bindShare(root, state);
    syncMoreButton(root, state);
}

function bindFilters(rootEl, state) {
    rootEl.querySelector('[data-filter-group="player"]')?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-filter]');
        const player = button?.getAttribute('data-filter');

        if (!button || !player || player === state.player || state.loading) {
            return;
        }

        state.player = player;
        state.page = 1;
        syncFilterButtons(rootEl, state);
        loadChallenges(rootEl, state, { replace: true });
    });
}

function bindLoadMore(rootEl, state) {
    rootEl.querySelector('[data-challenge-more]')?.addEventListener('click', () => {
        if (state.loading || !state.hasMore) {
            return;
        }

        state.page += 1;
        loadChallenges(rootEl, state, { replace: false });
    });
}

function bindShare(rootEl, state) {
    const modal = rootEl.querySelector('[data-share-modal]');
    const form = rootEl.querySelector('[data-share-form]');

    rootEl.querySelector('[data-challenge-grid]')?.addEventListener('click', (event) => {
        const card = event.target.closest('[data-challenge-card]');

        if (!card || state.loading) {
            return;
        }

        openShareModal(rootEl, state, card);
    });

    rootEl.querySelector('[data-share-cancel]')?.addEventListener('click', () => {
        modal?.close();
    });

    form?.addEventListener('submit', (event) => {
        event.preventDefault();
        submitShare(rootEl, state);
    });
}

function openShareModal(rootEl, state, card) {
    const modal = rootEl.querySelector('[data-share-modal]');
    const challengeId = card.getAttribute('data-challenge-id');
    const date = card.getAttribute('data-challenge-date') || '';
    const sourcePlayerId = card.getAttribute('data-player-id');

    if (!modal || !challengeId) {
        return;
    }

    state.shareChallengeId = challengeId;
    state.shareDate = date;
    rootEl.querySelector('[data-share-challenge-id]')?.setAttribute('value', challengeId);
    setShareError(rootEl, '');

    let available = 0;

    rootEl.querySelectorAll('[data-share-row]').forEach((row) => {
        const input = row.querySelector('[data-share-target]');
        const note = row.querySelector('[data-share-unavailable]');

        if (!(input instanceof HTMLInputElement)) {
            return;
        }

        input.checked = false;
        input.disabled = false;
        row.classList.remove('hidden');
        note?.classList.add('hidden');

        if (note) {
            note.textContent = '';
        }

        if (input.value === sourcePlayerId) {
            row.classList.add('hidden');
            input.disabled = true;

            return;
        }

        const dates = (input.getAttribute('data-dates') || '').split(',').filter(Boolean);

        if (date && dates.includes(date)) {
            input.disabled = true;

            if (note) {
                note.textContent = 'Already played this day';
                note.classList.remove('hidden');
            }

            return;
        }

        available += 1;
    });

    const submit = rootEl.querySelector('[data-share-submit]');

    if (submit instanceof HTMLButtonElement) {
        submit.disabled = available === 0;
    }

    modal.showModal();
}

async function submitShare(rootEl, state) {
    const url = rootEl.getAttribute('data-share-url');
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const selected = [...rootEl.querySelectorAll('[data-share-target]:checked:not(:disabled)')]
        .map((input) => Number(input.value))
        .filter((id) => Number.isInteger(id) && id > 0);
    const submit = rootEl.querySelector('[data-share-submit]');

    if (!url || !state.shareChallengeId) {
        return;
    }

    if (selected.length === 0) {
        setShareError(rootEl, 'Pick at least one player.');

        return;
    }

    if (submit instanceof HTMLButtonElement) {
        submit.disabled = true;
    }

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                challenge_id: Number(state.shareChallengeId),
                geoguesser_ids: selected,
            }),
        });
        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            setShareError(rootEl, firstError(payload) || 'Could not share this challenge.');

            return;
        }

        markShared(rootEl, state, selected, payload.message);
        rootEl.querySelector('[data-share-modal]')?.close();
    } catch {
        setShareError(rootEl, 'Could not share this challenge.');
    } finally {
        if (submit instanceof HTMLButtonElement) {
            submit.disabled = false;
        }
    }
}

function markShared(rootEl, state, selected, message) {
    const card = rootEl.querySelector(`[data-challenge-card][data-challenge-id="${state.shareChallengeId}"]`);
    card?.querySelector('[data-field="team"]')?.classList.remove('hidden');

    if (state.shareDate) {
        rootEl.querySelectorAll('[data-share-target]').forEach((input) => {
            if (!(input instanceof HTMLInputElement) || !selected.includes(Number(input.value))) {
                return;
            }

            const dates = (input.getAttribute('data-dates') || '').split(',').filter(Boolean);

            if (!dates.includes(state.shareDate)) {
                dates.push(state.shareDate);
                input.setAttribute('data-dates', dates.join(','));
            }
        });
    }

    const status = rootEl.querySelector('[data-share-status]');

    if (status && message) {
        status.textContent = message;
        status.classList.remove('hidden', 'text-error');
        status.classList.add('text-success');
    }
}

function setShareError(rootEl, message) {
    const alert = rootEl.querySelector('[data-share-error]');
    const text = alert?.querySelector('span');

    if (!alert) {
        return;
    }

    if (text) {
        text.textContent = message;
    }

    alert.classList.toggle('hidden', message === '');
}

function firstError(payload) {
    const errors = payload?.errors;

    if (!errors || typeof errors !== 'object') {
        return payload?.message || '';
    }

    const first = Object.values(errors)[0];

    if (Array.isArray(first)) {
        return first[0] || '';
    }

    return typeof first === 'string' ? first : '';
}

function syncFilterButtons(rootEl, state) {
    rootEl.querySelectorAll('[data-filter-group="player"] [data-filter]').forEach((button) => {
        const active = button.getAttribute('data-filter') === state.player;

        button.classList.toggle('btn-primary', active);
        button.classList.toggle('btn-ghost', !active);
        button.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
}

function syncMoreButton(rootEl, state) {
    const button = rootEl.querySelector('[data-challenge-more]');

    if (!button) {
        return;
    }

    button.disabled = state.loading;
    button.classList.toggle('hidden', !state.hasMore);
}

async function loadChallenges(rootEl, state, { replace }) {
    const url = rootEl.getAttribute('data-challenges-url');

    if (!url) {
        return;
    }

    const endpoint = new URL(url, window.location.origin);
    endpoint.searchParams.set('page', String(state.page));
    endpoint.searchParams.set('player', state.player);

    state.loading = true;
    syncMoreButton(rootEl, state);

    try {
        const response = await fetch(endpoint, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            if (!replace) {
                state.page = Math.max(1, state.page - 1);
            }

            return;
        }

        const payload = await response.json();
        const challenges = Array.isArray(payload.challenges) ? payload.challenges : [];

        state.hasMore = Boolean(payload.hasMore);
        renderChallenges(rootEl, challenges, replace);
    } catch {
        if (!replace) {
            state.page = Math.max(1, state.page - 1);
        }
    } finally {
        state.loading = false;
        syncMoreButton(rootEl, state);
    }
}

function renderChallenges(rootEl, challenges, replace) {
    const grid = rootEl.querySelector('[data-challenge-grid]');
    const empty = rootEl.querySelector('[data-challenge-empty]');
    const template = rootEl.querySelector('[data-challenge-card-template]');

    if (!grid || !template) {
        return;
    }

    if (replace) {
        grid.replaceChildren();
    }

    challenges.forEach((challenge) => {
        const card = challengeCard(template, challenge);

        if (card) {
            grid.append(card);
        }
    });

    const visible = grid.querySelectorAll('[data-challenge-card]').length;

    grid.classList.toggle('hidden', visible === 0);
    empty?.classList.toggle('hidden', visible > 0);
}

function challengeCard(template, challenge) {
    const card = template.content.firstElementChild?.cloneNode(true);

    if (!(card instanceof HTMLElement)) {
        return null;
    }

    card.setAttribute('data-challenge-id', String(challenge.id ?? ''));
    card.setAttribute('data-challenge-date', challenge.dateKey || '');
    card.setAttribute('data-player-id', String(challenge.playerId ?? ''));
    setField(card, 'date', challenge.date || 'Unknown date');
    setField(card, 'player', challenge.player || 'Unknown');
    setField(card, 'map', challenge.map || 'World');
    setField(card, 'score', formatScore(challenge.score));
    setField(card, 'meta', formatMeta(challenge.distance, challenge.steps));
    card.querySelector('[data-field="team"]')?.classList.toggle('hidden', !challenge.isDoneAsTeam);

    const swatch = card.querySelector('[data-field="color"]');

    if (swatch instanceof HTMLElement && typeof challenge.color === 'string') {
        swatch.style.background = challenge.color;
    }

    return card;
}

function setField(card, name, value) {
    const field = card.querySelector(`[data-field="${name}"]`);

    if (field) {
        field.textContent = value;
    }
}

function formatScore(score) {
    return typeof score === 'number' ? formatNumber(score) : 'Score pending';
}

function formatMeta(distance, steps) {
    const distanceLabel = typeof distance === 'number'
        ? `${formatNumber(distance / 1000, 1)} km`
        : 'Distance pending';
    const stepsLabel = typeof steps === 'number'
        ? `${formatNumber(steps)} steps`
        : 'Steps pending';

    return `${distanceLabel} · ${stepsLabel}`;
}

function formatNumber(value, fractionDigits = 0) {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: fractionDigits,
        maximumFractionDigits: fractionDigits,
    }).format(value);
}
