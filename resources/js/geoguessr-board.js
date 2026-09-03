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

    const palette = ['#FF8906', '#E53170', '#F25F4C', '#2CB67D', '#7F5AF0', '#FBBF24', '#3DA9FC'];
    const theme = readTheme();

    Chart.defaults.color = theme.muted;
    Chart.defaults.borderColor = theme.grid;
    Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;

    const draw = () => render(root, board, state, charts, theme, palette);

    bindTabs(root, draw);
    bindFilters(root, state, draw);

    if (root.querySelector('[data-tab="graphs"]')?.checked) {
        draw();
    } else {
        syncButtons(root, state);
    }
}

function readTheme() {
    const styles = getComputedStyle(document.documentElement);
    const content = styles.getPropertyValue('--color-base-content').trim() || '255 255 255';
    const primary = styles.getPropertyValue('--color-primary').trim() || '#FF8906';

    return {
        text: cssColor(content, 0.92),
        muted: cssColor(content, 0.62),
        grid: cssColor(content, 0.12),
        fill: cssColor(content, 0.16),
        primary,
    };
}

function cssColor(value, alpha) {
    if (value.startsWith('#') || value.startsWith('rgb')) {
        return value;
    }

    const parts = value.split(/[\s,/]+/).filter(Boolean);

    if (parts.length >= 3) {
        return `rgb(${parts[0]} ${parts[1]} ${parts[2]} / ${alpha})`;
    }

    return `rgb(255 255 255 / ${alpha})`;
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
                requestAnimationFrame(onShowGraphs);
            }

            window.history.replaceState({}, '', url);
        });
    });
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

    const values = rows.map((row) => metricValue(row, state.metric)).filter((value) => value !== null);
    const latest = rows.at(-1);
    const player = players.length === 1 ? players[0] : null;

    const cards = [
        { label: 'Best', value: values.length ? formatMetric(Math.max(...values), state.metric) : '—' },
        { label: 'Average', value: values.length ? formatMetric(average(values), state.metric) : '—' },
        { label: 'Games', value: String(values.length) },
        {
            label: player ? 'Streak' : 'Latest',
            value: player
                ? `${player.streak ?? 0} day${Number(player.streak) === 1 ? '' : 's'}`
                : latest
                    ? formatMetric(metricValue(latest, state.metric), state.metric)
                    : '—',
        },
    ];

    host.innerHTML = cards
        .map(
            (card) => `
            <article class="card bg-base-100 shadow-xl">
                <div class="card-body gap-1 p-4">
                    <p class="text-xs uppercase tracking-wide text-base-content/50">${card.label}</p>
                    <p class="text-xl font-bold tabular-nums">${card.value}</p>
                </div>
            </article>
        `,
        )
        .join('');
}

function renderTrend(rootEl, rows, players, state, charts, theme, palette) {
    const canvas = rootEl.querySelector('[data-chart="trend"]');
    const empty = rootEl.querySelector('[data-empty="trend"]');

    if (!canvas) {
        return;
    }

    const labels = [...new Set(rows.map((row) => row.date))].sort();
    const datasets = players.map((player, index) => {
        const color = palette[index % palette.length];

        return {
            label: player.nick || player.name,
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
        colors = sorted.map((_, index) => palette[index % palette.length]);

        if (title) {
            title.textContent = 'Each day';
        }

        if (copy) {
            copy.textContent = 'Every daily in this range.';
        }
    } else {
        labels = players.map((player) => player.nick || player.name);
        data = players.map((player) => {
            const values = rows
                .filter((row) => String(row.playerId) === String(player.id))
                .map((row) => metricValue(row, state.metric))
                .filter((value) => value !== null);

            return values.length ? average(values) : 0;
        });
        colors = players.map((_, index) => palette[index % palette.length]);

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
                    color: theme.muted,
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
        ticks: {
            color: theme.muted,
            maxTicksLimit: 5,
            callback: isMetricAxis ? (value) => formatCompact(value, metric) : undefined,
        },
    };
}

function toggleEmpty(canvas, empty, isEmpty) {
    canvas.parentElement?.classList.toggle('hidden', isEmpty);
    empty?.classList.toggle('hidden', !isEmpty);
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

    return 'Score';
}

function formatMetric(value, metric) {
    if (value === null || Number.isNaN(value)) {
        return '—';
    }

    if (metric === 'distance') {
        return `${(value / 1000).toLocaleString(undefined, { maximumFractionDigits: 1 })} km`;
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
