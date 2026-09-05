import Chart from 'chart.js/auto';

const root = document.querySelector('[data-geoguessr-board]');
const dataNode = document.querySelector('[data-geoguessr-data]');

if (root && dataNode) {
    const board = JSON.parse(dataNode.textContent || '{}');
    const state = {
        range: '7',
        player: 'all',
        metric: 'score',
    };
    const charts = {
        trend: null,
        compare: null,
    };

    const palette = ['#D82820', '#FEC523', '#283030', '#E85D04', '#9B2226', '#2A9D8F', '#F4A261'];
    const content = cssVarColor('--color-base-content');
    const theme = {
        text: content,
        muted: withAlpha(content, 0.72),
        grid: withAlpha(content, 0.14),
        primary: '#D82820',
    };

    Chart.defaults.color = theme.text;
    Chart.defaults.borderColor = theme.grid;
    Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
    Chart.defaults.font.size = 12;

    const draw = () => render(root, board, state, charts, theme, palette);

    bindTabs(root, draw);
    bindFilters(root, state, draw);
    bindRewards(root);

    if (root.querySelector('[data-tab="graphs"]')?.checked) {
        draw();
    } else {
        syncButtons(root, state);
    }
}

function cssVarColor(name) {
    const probe = document.createElement('span');
    probe.style.color = `var(${name})`;
    document.body.append(probe);
    const color = getComputedStyle(probe).color;
    probe.remove();

    return color || '#1c1c1c';
}

function withAlpha(color, alpha) {
    const parts = color.match(/[\d.]+/g);

    if (!parts || parts.length < 3) {
        return color;
    }

    return `rgba(${parts[0]}, ${parts[1]}, ${parts[2]}, ${alpha})`;
}

function bindTabs(rootEl, onShowGraphs) {
    rootEl.querySelectorAll('[data-tab]').forEach((tab) => {
        tab.addEventListener('change', () => {
            const url = new URL(window.location.href);
            const name = tab.getAttribute('data-tab');

            if (name === 'today') {
                url.searchParams.delete('tab');
            } else {
                url.searchParams.set('tab', name);

                if (name === 'graphs') {
                    requestAnimationFrame(onShowGraphs);
                }
            }

            window.history.replaceState({}, '', url);
        });
    });
}

function bindRewards(rootEl) {
    rootEl.addEventListener('click', (event) => {
        const button = event.target.closest('[data-reward]');

        if (!button) {
            return;
        }

        notifyReward(rootEl, button.getAttribute('data-reward') || '');
    });
}

function notifyReward(rootEl, message) {
    const host = rootEl.querySelector('[data-reward-toast]');

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

function bindFilters(rootEl, state, onChange) {
    rootEl.querySelectorAll('[data-filter-group]').forEach((group) => {
        const key = group.getAttribute('data-filter-group');

        group.addEventListener('click', (event) => {
            const button = event.target.closest('[data-filter]');

            if (!button || !key) {
                return;
            }

            state[key] = button.getAttribute('data-filter');
            onChange();
        });
    });
}

function render(rootEl, board, state, charts, theme, palette) {
    syncButtons(rootEl, state);

    const rows = filterRows(board, state);
    const players = visiblePlayers(board, state, rows);

    renderStats(rootEl, rows, players, state);
    renderTrend(rootEl, rows, players, state, charts, theme, palette);
    renderCompare(rootEl, rows, players, state, charts, theme, palette);
}

function syncButtons(rootEl, state) {
    rootEl.querySelectorAll('[data-filter-group]').forEach((group) => {
        const key = group.getAttribute('data-filter-group');

        group.querySelectorAll('[data-filter]').forEach((button) => {
            const active = button.getAttribute('data-filter') === state[key];
            button.classList.toggle('btn-primary', active);
            button.classList.toggle('btn-ghost', !active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    });
}

function filterRows(board, state) {
    const cutoff = rangeCutoff(state.range);

    return (board.challenges || []).filter((row) => {
        if (state.player !== 'all' && String(row.playerId) !== String(state.player)) {
            return false;
        }

        if (!row.date || metricValue(row, state.metric) === null) {
            return false;
        }

        return cutoff === null || row.date >= cutoff;
    });
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

function visiblePlayers(board, state, rows) {
    const ids = new Set(rows.map((row) => String(row.playerId)));

    return (board.players || []).filter((player) => {
        if (state.player !== 'all') {
            return String(player.id) === String(state.player);
        }

        return ids.has(String(player.id));
    });
}

function renderStats(rootEl, rows, players, state) {
    const host = rootEl.querySelector('[data-geoguessr-stats]');

    if (!host) {
        return;
    }

    const metric = state.metric;
    const values = rows.map((row) => metricValue(row, metric)).filter((value) => value !== null);
    const player = players.length === 1 ? players[0] : null;
    const best = pickBest(rows, players, metric);
    const latest = pickLatest(rows, players, metric);

    const cards = [
        {
            label: 'Best',
            value: best ? formatMetric(best.value, metric) : '—',
            who: best ? namesForPlayers(best.players) : null,
        },
        {
            label: 'Average',
            value: values.length ? formatMetric(average(values), metric) : '—',
            who: null,
        },
        {
            label: 'Games',
            value: String(values.length),
            who: null,
        },
        player
            ? {
                label: 'Streak',
                value: `${player.streak ?? 0} day${Number(player.streak) === 1 ? '' : 's'}`,
                who: playerLabel(player),
            }
            : {
                label: 'Latest',
                value: latest ? formatMetric(latest.value, metric) : '—',
                who: latest ? namesForPlayers(latest.players) : null,
            },
    ];

    host.innerHTML = cards
        .map(
            (card) => `
            <article class="card bg-base-100 shadow-xl">
                <div class="card-body gap-1 p-4">
                    <p class="text-xs uppercase tracking-wide text-base-content/60">${card.label}</p>
                    <p class="text-xl font-bold tabular-nums">${card.value}</p>
                    ${card.who ? `<p class="truncate text-xs text-base-content/60">${escapeHtml(card.who)}</p>` : ''}
                </div>
            </article>
        `,
        )
        .join('');
}

function isLowerBetter(metric) {
    return metric === 'distance';
}

function pickBest(rows, players, metric) {
    return pickByValue(
        rows.filter((row) => metricValue(row, metric) !== null),
        players,
        metric,
        (row) => metricValue(row, metric),
    );
}

function pickLatest(rows, players, metric) {
    const dated = rows.filter((row) => row.date && metricValue(row, metric) !== null);

    if (!dated.length) {
        return null;
    }

    const latestDate = dated.reduce((max, row) => (row.date > max ? row.date : max), dated[0].date);

    return pickBest(
        dated.filter((row) => row.date === latestDate),
        players,
        metric,
    );
}

function pickByValue(items, players, metric, valueOf) {
    const scored = items
        .map((item) => ({ item, value: valueOf(item) }))
        .filter((entry) => entry.value !== null);

    if (!scored.length) {
        return null;
    }

    const bestValue = isLowerBetter(metric)
        ? Math.min(...scored.map((entry) => entry.value))
        : Math.max(...scored.map((entry) => entry.value));
    const winners = scored.filter((entry) => entry.value === bestValue).map((entry) => entry.item);
    const named = uniquePlayers(winners, players);

    return named.length ? { value: bestValue, players: named } : null;
}

function uniquePlayers(items, players) {
    const ids = [
        ...new Set(
            items.map((item) => String(item.id ?? item.playerId)).filter((id) => id && id !== 'undefined'),
        ),
    ];

    return ids
        .map((id) => players.find((player) => String(player.id) === id))
        .filter(Boolean);
}

function namesForPlayers(players) {
    return players.map((player) => playerLabel(player)).join(', ');
}

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');
}

function renderTrend(rootEl, rows, players, state, charts, theme, palette) {
    const canvas = rootEl.querySelector('[data-chart="trend"]');
    const empty = rootEl.querySelector('[data-empty="trend"]');

    if (!canvas) {
        return;
    }

    const labels = [...new Set(rows.map((row) => row.date))].sort();
    const datasets = players.map((player, index) => {
        const color = player.color || palette[index % palette.length];

        return {
            label: playerLabel(player),
            data: labels.map((date) => {
                const match = rows.find((row) => String(row.playerId) === String(player.id) && row.date === date);

                return match ? metricValue(match, state.metric) : null;
            }),
            borderColor: color,
            backgroundColor: color,
            spanGaps: true,
            tension: 0.35,
            pointRadius: 4,
            pointHoverRadius: 6,
            borderWidth: 3,
            fill: false,
        };
    });

    toggleEmpty(canvas, empty, labels.length === 0);
    charts.trend = upsertChart(charts.trend, canvas, {
        type: 'line',
        data: { labels: labels.map(formatDate), datasets },
        options: chartOptions(theme, state.metric, false),
    });
}

function renderCompare(rootEl, rows, players, state, charts, theme, palette) {
    const canvas = rootEl.querySelector('[data-chart="compare"]');
    const empty = rootEl.querySelector('[data-empty="compare"]');
    const title = rootEl.querySelector('[data-compare-title]');
    const copy = rootEl.querySelector('[data-compare-copy]');

    if (!canvas) {
        return;
    }

    const singlePlayer = players.length === 1;
    let labels = [];
    let data = [];
    let colors = [];

    if (singlePlayer) {
        const sorted = [...rows].sort((a, b) => a.date.localeCompare(b.date));
        labels = sorted.map((row) => formatDate(row.date));
        data = sorted.map((row) => metricValue(row, state.metric));
        colors = sorted.map(() => players[0].color || palette[0]);

        if (title) {
            title.textContent = 'Each day';
        }

        if (copy) {
            copy.textContent = 'Every daily in this range.';
        }
    } else {
        labels = players.map((player) => playerLabel(player));
        data = players.map((player) => {
            const values = rows
                .filter((row) => String(row.playerId) === String(player.id))
                .map((row) => metricValue(row, state.metric))
                .filter((value) => value !== null);

            return values.length ? average(values) : 0;
        });
        colors = players.map((player, index) => player.color || palette[index % palette.length]);

        if (title) {
            title.textContent = 'Compare';
        }

        if (copy) {
            copy.textContent = 'Average for each player.';
        }
    }

    toggleEmpty(canvas, empty, data.length === 0);
    charts.compare = upsertChart(charts.compare, canvas, {
        type: 'bar',
        data: {
            labels,
            datasets: [
                {
                    label: metricLabel(state.metric),
                    data,
                    backgroundColor: colors.map((color) => `${color}CC`),
                    borderColor: colors,
                    borderWidth: 1,
                    borderRadius: 10,
                    barPercentage: 0.7,
                },
            ],
        },
        options: chartOptions(theme, state.metric, true),
    });
}

function upsertChart(existing, canvas, config) {
    if (existing) {
        existing.destroy();
    }

    return new Chart(canvas, config);
}

function chartOptions(theme, metric, horizontal) {
    return {
        responsive: true,
        maintainAspectRatio: false,
        indexAxis: horizontal ? 'y' : 'x',
        plugins: {
            legend: {
                display: !horizontal,
                position: 'bottom',
                labels: {
                    boxWidth: 10,
                    padding: 16,
                    color: theme.text,
                },
            },
            tooltip: {
                callbacks: {
                    label(context) {
                        const value = horizontal ? context.parsed.x : context.parsed.y;
                        const name = context.dataset.label ? `${context.dataset.label}: ` : '';

                        return `${name}${formatMetric(value, metric)}`;
                    },
                },
            },
        },
        scales: {
            x: axisOptions(theme, metric, horizontal),
            y: axisOptions(theme, metric, !horizontal),
        },
    };
}

function axisOptions(theme, metric, isMetricAxis) {
    return {
        beginAtZero: isMetricAxis,
        grid: {
            color: theme.grid,
            drawBorder: false,
        },
        ticks: isMetricAxis
            ? {
                color: theme.text,
                maxTicksLimit: 5,
                callback: (value) => formatCompact(value, metric),
            }
            : {
                color: theme.text,
                autoSkip: false,
                maxRotation: 0,
                callback(value) {
                    return this.getLabelForValue(value);
                },
            },
    };
}

function toggleEmpty(canvas, empty, isEmpty) {
    canvas.parentElement?.classList.toggle('hidden', isEmpty);
    empty?.classList.toggle('hidden', !isEmpty);
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

function metricValue(row, metric) {
    const value = row[metric];

    return typeof value === 'number' ? value : null;
}

function metricLabel(metric) {
    if (metric === 'distance') {
        return 'Distance';
    }

    if (metric === 'steps') {
        return 'Steps';
    }

    if (metric === 'xp') {
        return 'XP';
    }

    return 'Score';
}

function formatMetric(value, metric) {
    if (value === null || Number.isNaN(value)) {
        return '—';
    }

    if (metric === 'distance') {
        return `${(value / 1000).toLocaleString(undefined, { maximumFractionDigits: 1 })} km`;
    }

    if (metric === 'xp') {
        return `${Math.round(value).toLocaleString()} XP`;
    }

    return Math.round(value).toLocaleString();
}

function formatCompact(value, metric) {
    if (metric === 'distance') {
        return `${Math.round(value / 1000)}`;
    }

    if (value >= 1000) {
        return `${Math.round(value / 1000)}k`;
    }

    return String(value);
}

function formatDate(value) {
    const date = new Date(`${value}T00:00:00`);

    return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
}

function toIsoDate(date) {
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${date.getFullYear()}-${month}-${day}`;
}

function average(values) {
    return values.reduce((sum, value) => sum + value, 0) / values.length;
}
