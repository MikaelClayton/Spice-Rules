const root = document.querySelector('[data-spirdle-challenges]');
const dataNode = document.querySelector('[data-spirdle-dailies]');

if (root && dataNode) {
    const dailies = JSON.parse(dataNode.textContent || '[]');

    const show = (token) => renderChallenge(root, dailies, token);

    bindChallengeTabs(root, dailies, show);
    bindChallengeList(root, dailies, show);

    const initial = firstUnlockedToken(dailies, new URL(window.location.href).searchParams.get('challenge'));

    if (root.closest('[data-spirdle-board]')?.querySelector('[data-tab="challenges"]')?.checked && initial) {
        requestAnimationFrame(() => show(initial));
    }
}

function bindChallengeTabs(rootEl, dailies, show) {
    const board = rootEl.closest('[data-spirdle-board]');

    board?.querySelectorAll('[data-tab]').forEach((tab) => {
        tab.addEventListener('change', () => {
            if (tab.getAttribute('data-tab') !== 'challenges' || !tab.checked) {
                return;
            }

            const token = firstUnlockedToken(dailies, new URL(window.location.href).searchParams.get('challenge'));

            if (token) {
                requestAnimationFrame(() => show(token));
            }
        });
    });
}

function bindChallengeList(rootEl, dailies, show) {
    rootEl.querySelector('[data-challenge-list]')?.addEventListener('click', (event) => {
        const button = event.target instanceof Element ? event.target.closest('[data-challenge-token]') : null;

        if (!button) {
            return;
        }

        if (button.getAttribute('data-locked') === 'true') {
            notifyCheeky(rootEl);
            return;
        }

        show(button.getAttribute('data-challenge-token'));
    });
}

function firstUnlockedToken(dailies, preferred) {
    const requested = dailies.find((item) => item.token === preferred && !item.locked);

    if (requested) {
        return requested.token;
    }

    return dailies.find((item) => !item.locked)?.token ?? null;
}

function notifyCheeky(rootEl) {
    const host = rootEl.querySelector('[data-challenge-toast]');

    if (!host) {
        return;
    }

    host.replaceChildren();
    const alert = document.createElement('div');
    alert.setAttribute('role', 'alert');
    alert.className = 'alert alert-warning shadow-lg';
    const message = document.createElement('span');
    message.textContent = 'Cheeky 👀 try today’s word first.';
    alert.append(message);
    host.append(alert);
    window.setTimeout(() => alert.remove(), 4000);
}

function renderChallenge(rootEl, dailies, token) {
    const daily = dailies.find((item) => item.token === token);

    if (!daily) {
        return;
    }

    const url = new URL(window.location.href);
    url.searchParams.set('tab', 'challenges');
    url.searchParams.set('challenge', token);
    window.history.replaceState({}, '', url);

    rootEl.querySelectorAll('[data-challenge-token]').forEach((button) => {
        const active = button.getAttribute('data-challenge-token') === token;
        button.classList.toggle('btn-primary', active);
        button.classList.toggle('btn-ghost', !active);
        button.setAttribute('aria-pressed', active ? 'true' : 'false');
    });

    const title = rootEl.querySelector('[data-challenge-title]');
    const copy = rootEl.querySelector('[data-challenge-copy]');
    const word = rootEl.querySelector('[data-challenge-word]');

    if (title) {
        title.textContent = daily.label;
    }

    if (copy) {
        const count = daily.playerCount;
        copy.textContent = `${count} player${count === 1 ? '' : 's'} · attempts, missed tries, and times.`;
    }

    if (word) {
        word.textContent = daily.word ? String(daily.word).toUpperCase() : '';
    }

    renderResults(rootEl, daily);
}

function renderResults(rootEl, daily) {
    rootEl.querySelectorAll('[data-challenge-results-panel]').forEach((panel) => {
        panel.hidden = panel.getAttribute('data-challenge-results-panel') !== daily.token;
    });
}
