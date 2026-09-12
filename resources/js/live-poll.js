document.querySelectorAll('[data-live-poll]').forEach((root) => {
    if (root instanceof HTMLElement) {
        bindLivePoll(root);
    }
});

function headers() {
    return {
        Accept: 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        'X-Requested-With': 'XMLHttpRequest',
    };
}

function bindLivePoll(root) {
    const pollUrl = root.getAttribute('data-poll-url');

    if (!pollUrl) {
        return;
    }

    let revision = root.getAttribute('data-revision') || '';
    let inFlight = false;

    const poll = async () => {
        if (document.hidden || inFlight) {
            return;
        }

        if (root.querySelector('[data-live-region] form[data-submitting="true"]')) {
            return;
        }

        inFlight = true;

        try {
            const url = new URL(pollUrl, window.location.origin);

            if (revision) {
                url.searchParams.set('revision', revision);
            }

            const response = await fetch(url, {
                headers: headers(),
                credentials: 'same-origin',
            });

            if (!response.ok) {
                return;
            }

            const data = await response.json();

            if (!data?.revision || data.revision === revision || !data.regions) {
                revision = data?.revision || revision;
                root.setAttribute('data-revision', revision);

                return;
            }

            revision = data.revision;
            root.setAttribute('data-revision', revision);
            applyRegions(root, data.regions);
        } catch {
            return;
        } finally {
            inFlight = false;
        }
    };

    window.setInterval(poll, 4000);
}

function applyRegions(root, regions) {
    Object.entries(regions).forEach(([name, html]) => {
        const node = root.querySelector(`[data-live-region="${name}"]`);

        if (!(node instanceof HTMLElement) || typeof html !== 'string') {
            return;
        }

        const openIds = [...node.querySelectorAll('details[open][data-player]')].map(
            (el) => el.getAttribute('data-player') || '',
        );

        node.innerHTML = html;

        openIds.forEach((id) => {
            if (!id) {
                return;
            }

            node.querySelector(`details[data-player="${cssEscape(id)}"]`)?.setAttribute('open', '');
        });
    });
}

function cssEscape(value) {
    if (window.CSS?.escape) {
        return window.CSS.escape(value);
    }

    return value.replace(/\\/g, '\\\\').replace(/"/g, '\\"');
}
