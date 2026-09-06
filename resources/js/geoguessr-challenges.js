import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { bindMapFullscreen } from './geoguessr-map-fullscreen';

const root = document.querySelector('[data-geoguessr-challenges]');
const dataNode = document.querySelector('[data-geoguessr-dailies]');

if (root && dataNode) {
    const dailies = JSON.parse(dataNode.textContent || '[]');
    const palette = ['#2A9D8F', '#F4A261', '#E85D04', '#283030', '#9B2226', '#FEC523'];
    let map = null;
    let layer = null;

    const show = (token) => renderChallenge(root, dailies, token, palette, () => {
        if (!map) {
            map = L.map(root.querySelector('[data-challenge-map]'), {
                scrollWheelZoom: false,
            });
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap',
                maxZoom: 18,
            }).addTo(map);
        }

        return map;
    }, (nextLayer) => {
        if (layer) {
            layer.remove();
        }

        layer = nextLayer;
    });

    bindChallengeTabs(root, dailies, show);
    bindChallengeList(root, dailies, show);

    const initial = firstUnlockedToken(dailies, new URL(window.location.href).searchParams.get('challenge'));

    bindMapFullscreen(
        root.querySelector('[data-challenge-map-wrap]'),
        root.querySelector('[data-challenge-fullscreen]'),
        () => {
            if (!map && initial) {
                show(initial);
            }

            return map;
        },
    );

    if (root.closest('[data-geoguessr-board]')?.querySelector('[data-tab="challenges"]')?.checked && initial) {
        requestAnimationFrame(() => show(initial));
    }
}

function bindChallengeTabs(rootEl, dailies, show) {
    const board = rootEl.closest('[data-geoguessr-board]');

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
        const button = event.target.closest('[data-challenge-token]');

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
    message.textContent = 'Cheeky 👀 trying to view the locations before you’ve done the daily.';
    alert.append(message);
    host.append(alert);
    window.setTimeout(() => alert.remove(), 4000);
}

function renderChallenge(rootEl, dailies, token, palette, mapOf, setLayer) {
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

    if (title) {
        title.textContent = daily.label;
    }

    if (copy) {
        copy.textContent = `${daily.mapName} · ${daily.playerCount} player${daily.playerCount === 1 ? '' : 's'}. Red is the real location.`;
    }

    renderSummary(rootEl, daily);
    renderRoundTable(rootEl, daily);
    renderMap(rootEl, daily, palette, mapOf, setLayer);
}

function renderMap(rootEl, daily, palette, mapOf, setLayer) {
    const host = rootEl.querySelector('[data-challenge-map]');

    if (!host) {
        return;
    }

    const map = mapOf();
    const group = L.layerGroup();
    const bounds = [];
    const colors = playerColors(daily, palette);

    daily.rounds.forEach((round) => {
        if (round.actualLat == null || round.actualLng == null) {
            return;
        }

        const actual = [round.actualLat, round.actualLng];
        bounds.push(actual);
        marker(actual, '#D82820', String(round.number), `Round ${round.number} · ${countryName(round.country)}`).addTo(group);

        round.guesses.forEach((guess) => {
            const point = [guess.lat, guess.lng];
            bounds.push(point);
            marker(point, colors.get(String(guess.playerId)) || '#2A9D8F', guess.initials || guess.label, `${guess.label} · round ${round.number} · ${formatScore(guess.score)}`).addTo(group);
            L.polyline([point, actual], {
                color: colors.get(String(guess.playerId)) || '#2A9D8F',
                weight: 2,
                opacity: 0.7,
            }).addTo(group);
        });
    });

    group.addTo(map);
    setLayer(group);

    requestAnimationFrame(() => {
        map.invalidateSize();

        if (bounds.length) {
            map.fitBounds(bounds, { padding: [24, 24], maxZoom: 6 });
        }
    });
}

function marker(latlng, color, label, title) {
    const text = String(label || '?');
    const wide = text.length > 1;
    const pin = L.marker(latlng, {
        title: title || text,
        icon: L.divIcon({
            className: 'geoguessr-pin',
            html: `<span style="background:${color}">${escapeHtml(text)}</span>`,
            iconSize: wide ? [32, 28] : [28, 28],
            iconAnchor: wide ? [16, 14] : [14, 14],
        }),
    });

    return title ? pin.bindPopup(title) : pin;
}

function playerColors(daily, palette) {
    const colors = new Map();
    let index = 0;

    daily.rounds.forEach((round) => {
        round.guesses.forEach((guess) => {
            const id = String(guess.playerId);

            if (!colors.has(id)) {
                colors.set(id, guess.color || palette[index % palette.length]);
                index += 1;
            }
        });
    });

    return colors;
}

function renderSummary(rootEl, daily) {
    const host = rootEl.querySelector('[data-challenge-summary]');

    if (!host) {
        return;
    }

    const standings = Array.isArray(daily.standings) ? daily.standings : [];

    if (standings.length === 0) {
        host.innerHTML = '<p class="text-sm text-base-content/60">No scores for this daily yet.</p>';

        return;
    }

    const winners = standings.filter((row) => row.place === 1);
    const names = winners.map((row) => row.label).join(' and ');
    const headline = winners.length > 1 ? `${names} tied for the day` : `${names} won the day`;

    host.innerHTML = `
        <p class="text-lg font-semibold">${escapeHtml(headline)}</p>
        <p class="text-sm text-base-content/70">${formatScore(winners[0]?.score)}</p>
        <ol class="mt-3 space-y-1.5">
            ${standings
                .map(
                    (row) => `
                <li class="flex items-center gap-2 text-sm">
                    <span class="badge badge-sm tabular-nums ${placeBadge(row.place)}">${row.place}</span>
                    <span class="inline-block h-2.5 w-2.5 shrink-0 rounded-full" style="background:${row.color || '#2A9D8F'}"></span>
                    <span class="min-w-0 flex-1 truncate">${escapeHtml(row.label)}</span>
                    <span class="tabular-nums font-semibold">${formatScore(row.score)}</span>
                </li>
            `,
                )
                .join('')}
        </ol>
    `;
}

function renderRoundTable(rootEl, daily) {
    const host = rootEl.querySelector('[data-challenge-rounds]');

    if (!host) {
        return;
    }

    if (!daily.rounds.length) {
        host.innerHTML = '<p class="text-sm text-base-content/60">No rounds for this daily yet.</p>';

        return;
    }

    host.innerHTML = daily.rounds
        .map((round) => {
            const guesses = rankedGuesses(round.guesses || []);
            const winners = guesses.filter((guess) => guess.place === 1).map((guess) => guess.label);
            const winnerLabel = winners.length ? ` · ${winners.join(' and ')}` : '';

            return `
            <article class="mb-4 last:mb-0">
                <p class="mb-2 font-semibold">Round ${round.number}${countryName(round.country) ? ` · ${countryName(round.country)}` : ''}${escapeHtml(winnerLabel)}</p>
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th></th>
                                <th>Player</th>
                                <th>Score</th>
                                <th>%</th>
                                <th>Distance</th>
                                <th>Time</th>
                                <th>Steps</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${guesses
                                .map(
                                    (guess) => `
                                <tr class="${guess.place === 1 ? 'font-semibold' : ''}">
                                    <td class="w-10">
                                        <span class="badge badge-sm tabular-nums ${placeBadge(guess.place)}">${guess.place}</span>
                                    </td>
                                    <td>
                                        <span class="mr-2 inline-block h-2.5 w-2.5 rounded-full" style="background:${guess.color || '#2A9D8F'}"></span>
                                        ${escapeHtml(guess.label)}
                                    </td>
                                    <td class="tabular-nums">${formatScore(guess.score)}</td>
                                    <td class="tabular-nums">${guess.percent == null ? '—' : `${Math.round(guess.percent)}%`}</td>
                                    <td class="tabular-nums">${formatKm(guess.distance)}</td>
                                    <td class="tabular-nums">${guess.time == null ? '—' : `${guess.time}s`}</td>
                                    <td class="tabular-nums">${guess.steps == null ? '—' : guess.steps}</td>
                                </tr>
                            `,
                                )
                                .join('')}
                        </tbody>
                    </table>
                </div>
            </article>
        `;
        })
        .join('');
}

function rankedGuesses(guesses) {
    const sorted = [...guesses].sort((left, right) => (right.score ?? -1) - (left.score ?? -1));
    let place = 1;
    let seen = 0;
    let previous = null;

    return sorted.map((guess) => {
        seen += 1;

        if (guess.score !== previous) {
            place = seen;
        }

        previous = guess.score;

        return { ...guess, place };
    });
}

function placeBadge(place) {
    if (place === 1) {
        return 'badge-warning';
    }

    if (place === 2) {
        return 'badge-ghost';
    }

    if (place === 3) {
        return 'badge-accent';
    }

    return 'badge-neutral';
}

function countryName(code) {
    if (!code) {
        return '';
    }

    try {
        return new Intl.DisplayNames(['en'], { type: 'region' }).of(code.toUpperCase()) || code.toUpperCase();
    } catch {
        return code.toUpperCase();
    }
}

function formatScore(value) {
    return value == null ? '—' : Number(value).toLocaleString();
}

function formatKm(meters) {
    if (meters == null) {
        return '—';
    }

    return `${(meters / 1000).toLocaleString(undefined, { maximumFractionDigits: 1 })} km`;
}

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');
}
