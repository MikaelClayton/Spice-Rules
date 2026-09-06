import Chart from 'chart.js/auto';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { bindMapFullscreen, exitMapFullscreen } from './geoguessr-map-fullscreen';

const maps = {
    countries: null,
    guesses: null,
};
const layers = {
    countries: null,
    guesses: null,
};
let roundChart = null;

export function renderInsights(rootEl, board, insights, state, theme, palette) {
    const cutoff = rangeCutoff(state.range);
    const challenges = (board.challenges || []).filter((row) => matchesRange(row, cutoff, state.player));
    const rounds = (insights.rounds || []).filter((row) => matchesRange(row, cutoff, state.player));
    const matchups = (insights.rounds || []).filter((row) => matchesRange(row, cutoff, 'all'));
    const players = visiblePlayers(board, state, challenges);

    renderCalendar(rootEl, players, challenges);
    renderRoundChart(rootEl, players, rounds, state, theme, palette);
    renderHeadToHead(rootEl, board, matchups, state);
    renderCountryHeat(rootEl, rounds);
    renderGuessHeat(rootEl, board, rounds);
    renderRegionBoard(rootEl, board, rounds, {
        host: '[data-insight="continents"]',
        empty: '[data-empty="continent-board"]',
        field: 'continent',
        header: 'Continent',
        label: (value) => value,
    });
    renderRegionBoard(rootEl, board, rounds, {
        host: '[data-insight="countries"]',
        empty: '[data-empty="country-board"]',
        field: 'country',
        header: 'Country',
        label: countryName,
    });
    bindInsightFullscreen(rootEl, 'countries');
    bindInsightFullscreen(rootEl, 'guesses');
}

function matchesRange(row, cutoff, player) {
    if (!row.date) {
        return false;
    }

    if (cutoff !== null && row.date < cutoff) {
        return false;
    }

    return player === 'all' || String(row.playerId) === String(player);
}

function rangeCutoff(range) {
    if (range === 'all') {
        return null;
    }

    const days = Number(range);
    const date = new Date();
    date.setHours(0, 0, 0, 0);
    date.setDate(date.getDate() - (days - 1));

    return toIsoDate(date);
}

function toIsoDate(date) {
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${date.getFullYear()}-${month}-${day}`;
}

function visiblePlayers(board, state, challenges) {
    const ids = new Set(challenges.map((row) => String(row.playerId)));

    return (board.players || []).filter((player) => {
        if (state.player !== 'all') {
            return String(player.id) === String(state.player);
        }

        return ids.has(String(player.id));
    });
}

function renderCalendar(rootEl, players, challenges) {
    const host = rootEl.querySelector('[data-insight="calendar"]');
    const empty = rootEl.querySelector('[data-empty="calendar"]');
    const dates = [...new Set(challenges.map((row) => row.date).filter(Boolean))].sort();

    if (!host) {
        return;
    }

    const isEmpty = dates.length === 0 || players.length === 0;
    host.classList.toggle('hidden', isEmpty);
    empty?.classList.toggle('hidden', !isEmpty);

    if (isEmpty) {
        host.replaceChildren();

        return;
    }

    const scores = new Map(
        challenges
            .filter((row) => row.date && row.score !== null && row.score !== undefined)
            .map((row) => [`${row.playerId}:${row.date}`, row.score]),
    );

    host.innerHTML = `
        <table class="table table-xs">
            <thead>
                <tr>
                    <th class="sticky left-0 z-10 bg-base-100">Player</th>
                    ${dates.map((date) => `<th class="px-1 text-center font-medium">${escapeHtml(shortDate(date))}</th>`).join('')}
                </tr>
            </thead>
            <tbody>
                ${players.map((player) => `
                    <tr>
                        <th class="sticky left-0 z-10 bg-base-100 font-medium">${escapeHtml(playerLabel(player))}</th>
                        ${dates.map((date) => {
                            const score = scores.get(`${player.id}:${date}`);
                            const title = score == null ? 'No game' : `${playerLabel(player)} · ${formatNumber(score)}`;

                            return `<td class="px-1">
                                <span class="score-heat-cell" style="background:${scoreColor(score)}" title="${escapeHtml(title)}"></span>
                            </td>`;
                        }).join('')}
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
}

function renderRoundChart(rootEl, players, rounds, state, theme, palette) {
    const canvas = rootEl.querySelector('[data-chart="rounds"]');
    const empty = rootEl.querySelector('[data-empty="rounds"]');

    if (!canvas) {
        return;
    }

    const labels = ['1', '2', '3', '4', '5'];
    const scored = rounds.filter((row) => row.round >= 1 && row.round <= 5 && typeof row.score === 'number');
    const isEmpty = scored.length === 0 || players.length === 0;

    canvas.parentElement?.classList.toggle('hidden', isEmpty);
    empty?.classList.toggle('hidden', !isEmpty);

    if (isEmpty) {
        roundChart?.destroy();
        roundChart = null;

        return;
    }

    const datasets = players.map((player, index) => {
        const color = player.color || palette[index % palette.length];

        return {
            label: playerLabel(player),
            data: labels.map((round) => {
                const values = scored
                    .filter((row) => String(row.playerId) === String(player.id) && String(row.round) === round)
                    .map((row) => row.score);

                return values.length ? average(values) : null;
            }),
            backgroundColor: `${color}CC`,
            borderColor: color,
            borderWidth: 1,
            borderRadius: 6,
        };
    });

    const config = {
        type: 'bar',
        data: { labels: labels.map((round) => `Round ${round}`), datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: state.player === 'all',
                    position: 'bottom',
                    labels: { boxWidth: 10, padding: 16, color: theme.text },
                },
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { color: theme.text },
                },
                y: {
                    beginAtZero: true,
                    suggestedMax: 5000,
                    grid: { color: theme.grid, drawBorder: false },
                    ticks: { color: theme.text, maxTicksLimit: 5 },
                },
            },
        },
    };

    if (roundChart) {
        roundChart.destroy();
    }

    roundChart = new Chart(canvas, config);
}

function renderHeadToHead(rootEl, board, rounds, state) {
    const host = rootEl.querySelector('[data-insight="head-to-head"]');
    const empty = rootEl.querySelector('[data-empty="head-to-head"]');

    if (!host) {
        return;
    }

    const matrix = matchupMatrix(rounds);
    const ids = playerIdsForMatchups(board, state, matrix);
    const isEmpty = ids.length < 2;

    host.classList.toggle('hidden', isEmpty);
    empty?.classList.toggle('hidden', !isEmpty);

    if (isEmpty) {
        host.replaceChildren();

        return;
    }

    host.innerHTML = `
        <table class="table table-sm">
            <thead>
                <tr>
                    <th></th>
                    ${ids.map((id) => `<th class="text-center">${escapeHtml(nameOf(board, id))}</th>`).join('')}
                </tr>
            </thead>
            <tbody>
                ${ids.map((rowId) => `
                    <tr>
                        <th class="font-medium">${escapeHtml(nameOf(board, rowId))}</th>
                        ${ids.map((colId) => {
                            if (String(rowId) === String(colId)) {
                                return '<td class="bg-base-200"></td>';
                            }

                            const wins = matrix.get(key(rowId, colId)) || 0;
                            const losses = matrix.get(key(colId, rowId)) || 0;

                            return `<td class="text-center tabular-nums ${wins > losses ? 'font-semibold text-success' : ''}">${wins}–${losses}</td>`;
                        }).join('')}
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
}

function matchupMatrix(rounds) {
    const grouped = new Map();

    rounds.forEach((row) => {
        if (row.token == null || row.round == null || typeof row.score !== 'number') {
            return;
        }

        const groupKey = `${row.token}:${row.round}`;
        const group = grouped.get(groupKey) || [];
        group.push(row);
        grouped.set(groupKey, group);
    });

    const wins = new Map();

    grouped.forEach((group) => {
        for (let i = 0; i < group.length; i += 1) {
            for (let j = i + 1; j < group.length; j += 1) {
                const left = group[i];
                const right = group[j];

                if (left.score === right.score) {
                    continue;
                }

                const winner = left.score > right.score ? left.playerId : right.playerId;
                const loser = left.score > right.score ? right.playerId : left.playerId;
                const winKey = key(winner, loser);
                wins.set(winKey, (wins.get(winKey) || 0) + 1);
            }
        }
    });

    return wins;
}

function playerIdsForMatchups(board, state, matrix) {
    const ids = [...new Set(
        [...matrix.keys()].flatMap((entry) => entry.split(':')),
    )];

    const ordered = (board.players || [])
        .map((player) => String(player.id))
        .filter((id) => ids.includes(id));

    if (state.player === 'all') {
        return ordered;
    }

    const selected = String(state.player);

    if (!ordered.includes(selected)) {
        return [];
    }

    return [selected, ...ordered.filter((id) => id !== selected)];
}

function renderCountryHeat(rootEl, rounds) {
    const wrap = insightWrap(rootEl, 'countries');
    const empty = rootEl.querySelector('[data-empty="countries"]');
    const stats = groupStats(rounds, 'country');
    const isEmpty = stats.length === 0;

    if (isEmpty) {
        exitMapFullscreen(wrap);
    }

    wrap?.classList.toggle('hidden', isEmpty);
    empty?.classList.toggle('hidden', !isEmpty);

    if (isEmpty) {
        clearMap('countries');

        return;
    }

    const map = ensureMap(rootEl, 'countries');

    if (!map) {
        return;
    }

    const group = L.layerGroup();
    const bounds = [];

    stats.forEach((entry) => {
        if (entry.lat == null || entry.lng == null) {
            return;
        }

        const point = [entry.lat, entry.lng];
        bounds.push(point);
        L.circleMarker(point, {
            radius: Math.min(36, 10 + Math.sqrt(entry.rounds) * 6),
            color: heatColor(entry.avgScore),
            fillColor: heatColor(entry.avgScore),
            fillOpacity: 0.72,
            weight: 1,
        })
            .bindPopup(`${countryName(entry.id)} · ${entry.rounds} round${entry.rounds === 1 ? '' : 's'} · avg ${formatNumber(entry.avgScore)}`)
            .addTo(group);
    });

    setLayer(map, 'countries', group, bounds, 4);
}

function renderGuessHeat(rootEl, board, rounds) {
    const wrap = insightWrap(rootEl, 'guesses');
    const empty = rootEl.querySelector('[data-empty="guesses"]');
    const guesses = rounds.filter((row) => row.guessLat != null && row.guessLng != null);
    const isEmpty = guesses.length === 0;

    if (isEmpty) {
        exitMapFullscreen(wrap);
    }

    wrap?.classList.toggle('hidden', isEmpty);
    empty?.classList.toggle('hidden', !isEmpty);

    if (isEmpty) {
        clearMap('guesses');

        return;
    }

    const map = ensureMap(rootEl, 'guesses');

    if (!map) {
        return;
    }

    const group = L.layerGroup();
    const bounds = [];

    guesses.forEach((row) => {
        const point = [row.guessLat, row.guessLng];
        const player = (board.players || []).find((item) => String(item.id) === String(row.playerId));
        const color = player?.color || '#d82820';
        bounds.push(point);
        L.circleMarker(point, {
            radius: 18,
            stroke: false,
            fillColor: color,
            fillOpacity: 0.16,
        }).addTo(group);
        L.circleMarker(point, {
            radius: 7,
            stroke: false,
            fillColor: color,
            fillOpacity: 0.42,
        }).bindPopup(`${playerLabel(player || { name: 'Unknown' })} · ${formatNumber(row.score)}`).addTo(group);
    });

    setLayer(map, 'guesses', group, bounds, 5);
}

function renderRegionBoard(rootEl, board, rounds, { host, empty, field, header, label }) {
    const table = rootEl.querySelector(host);
    const emptyEl = rootEl.querySelector(empty);
    const stats = groupStats(rounds, field);

    if (!table) {
        return;
    }

    const isEmpty = stats.length === 0;
    table.classList.toggle('hidden', isEmpty);
    emptyEl?.classList.toggle('hidden', !isEmpty);

    if (isEmpty) {
        table.replaceChildren();

        return;
    }

    table.innerHTML = `
        <table class="table table-sm">
            <thead>
                <tr>
                    <th>${escapeHtml(header)}</th>
                    <th>Rounds</th>
                    <th>Avg score</th>
                    <th>Avg distance</th>
                    <th>Best</th>
                </tr>
            </thead>
            <tbody>
                ${stats.map((entry) => `
                    <tr>
                        <td>${escapeHtml(label(entry.id))}</td>
                        <td class="tabular-nums">${entry.rounds}</td>
                        <td class="tabular-nums">${formatNumber(entry.avgScore)}</td>
                        <td class="tabular-nums">${formatKm(entry.avgDistance)}</td>
                        <td>${escapeHtml(nameOf(board, entry.bestPlayerId))}</td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
}

function bindInsightFullscreen(rootEl, key) {
    const wrap = insightWrap(rootEl, key);
    const button = wrap?.querySelector('[data-map-fullscreen]');

    bindMapFullscreen(wrap, button, () => maps[key]);
}

function insightWrap(rootEl, key) {
    return rootEl.querySelector(`[data-insight-map-wrap="${key}"]`)
        ?? document.querySelector(`[data-insight-map-wrap="${key}"]`);
}

function groupStats(rounds, field) {
    const grouped = new Map();

    rounds.forEach((row) => {
        const id = row[field];

        if (!id) {
            return;
        }

        const entry = grouped.get(id) || {
            id,
            scores: [],
            distances: [],
            lats: [],
            lngs: [],
            byPlayer: new Map(),
        };

        if (typeof row.score === 'number') {
            entry.scores.push(row.score);
            const playerScores = entry.byPlayer.get(String(row.playerId)) || [];
            playerScores.push(row.score);
            entry.byPlayer.set(String(row.playerId), playerScores);
        }

        if (typeof row.distance === 'number') {
            entry.distances.push(row.distance);
        }

        if (row.actualLat != null && row.actualLng != null) {
            entry.lats.push(row.actualLat);
            entry.lngs.push(row.actualLng);
        }

        grouped.set(id, entry);
    });

    return [...grouped.values()]
        .map((entry) => {
            const best = [...entry.byPlayer.entries()]
                .map(([playerId, scores]) => ({ playerId, avg: average(scores) }))
                .sort((left, right) => right.avg - left.avg)[0];

            return {
                id: entry.id,
                rounds: entry.scores.length,
                avgScore: average(entry.scores),
                avgDistance: entry.distances.length ? average(entry.distances) : null,
                lat: entry.lats.length ? average(entry.lats) : null,
                lng: entry.lngs.length ? average(entry.lngs) : null,
                bestPlayerId: best?.playerId ?? null,
            };
        })
        .sort((left, right) => right.avgScore - left.avgScore);
}

function ensureMap(rootEl, key) {
    const el = insightWrap(rootEl, key)?.querySelector(`[data-insight-map="${key}"]`);

    if (!el) {
        return null;
    }

    if (maps[key] && maps[key].getContainer() === el) {
        return maps[key];
    }

    maps[key]?.remove();
    maps[key] = L.map(el, {
        scrollWheelZoom: false,
        worldCopyJump: true,
    });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap',
        maxZoom: 18,
    }).addTo(maps[key]);

    return maps[key];
}

function setLayer(map, key, group, bounds, maxZoom) {
    layers[key]?.remove();
    layers[key] = group;
    group.addTo(map);

    requestAnimationFrame(() => {
        map.invalidateSize();

        if (bounds.length === 1) {
            map.setView(bounds[0], Math.min(maxZoom, 3));
        } else if (bounds.length) {
            map.fitBounds(bounds, { padding: [28, 28], maxZoom });
        }
    });
}

function clearMap(key) {
    layers[key]?.remove();
    layers[key] = null;
}

function key(left, right) {
    return `${left}:${right}`;
}

function nameOf(board, id) {
    const player = (board.players || []).find((item) => String(item.id) === String(id));

    return player ? playerLabel(player) : 'Unknown';
}

function playerLabel(player) {
    if (player.label) {
        return player.label;
    }

    const name = player.name || player.nick || 'Unknown';
    const nick = player.nick;

    if (nick && nick !== name) {
        return `${name} (${nick})`;
    }

    return name;
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

function scoreColor(score) {
    if (score == null) {
        return 'color-mix(in oklab, var(--color-base-content) 8%, transparent)';
    }

    const t = Math.min(1, Math.max(0, Number(score) / 25000));

    return `color-mix(in oklab, var(--color-primary) ${Math.round(t * 100)}%, var(--color-base-300))`;
}

function heatColor(score) {
    const t = Math.min(1, Math.max(0, Number(score || 0) / 25000));
    const start = [234, 217, 180];
    const end = [216, 40, 32];
    const mix = start.map((channel, index) => Math.round(channel + (end[index] - channel) * t));

    return `rgb(${mix.join(',')})`;
}

function shortDate(value) {
    const date = new Date(`${value}T00:00:00`);

    return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
}

function formatNumber(value) {
    return Math.round(Number(value) || 0).toLocaleString();
}

function formatKm(meters) {
    if (meters == null || Number.isNaN(meters)) {
        return '—';
    }

    return `${(meters / 1000).toLocaleString(undefined, { maximumFractionDigits: 1 })} km`;
}

function average(values) {
    return values.reduce((sum, value) => sum + value, 0) / values.length;
}

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');
}
