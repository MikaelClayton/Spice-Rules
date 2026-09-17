const board = document.querySelector('[data-spirdle-board]');

if (board instanceof HTMLElement) {
    board.querySelectorAll('[data-tab]').forEach((tab) => {
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
        });
    });

    board.addEventListener('click', (event) => {
        const button = event.target instanceof Element ? event.target.closest('[data-reward]') : null;

        if (!(button instanceof HTMLElement)) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        notifyReward(board, button.getAttribute('data-reward') || '');
    });
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
