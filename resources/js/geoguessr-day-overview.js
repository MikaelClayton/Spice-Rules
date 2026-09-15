import Chart from 'chart.js/auto';

let theme = null;

export function bindTodayOverview() {
    const section = document.querySelector('[data-day-overview="today"]');
    const dataNode = document.querySelector('[data-geoguessr-today-overview]');
    const pollRoot = document.querySelector('[data-live-poll]');

    if (!section) {
        return;
    }

    const charts = {};
    let payload = parseOverview(dataNode?.textContent);
    const draw = () => renderDayOverview(section, payload, charts);

    draw();

    pollRoot?.addEventListener('live-poll:updated', (event) => {
        if (!event.detail?.todayOverview) {
            return;
        }

        payload = event.detail.todayOverview;
        draw();
    });

    bindTabResize(section, 'today', charts);
}

export function overviewFromDaily(daily) {
    const roundNumbers = [...new Set((daily?.rounds || []).map((round) => Number(round.number)))]
        .filter((number) => Number.isFinite(number) && number > 0)
        .sort((left, right) => left - right);
    const rounds = roundNumbers.length ? roundNumbers : [1, 2, 3, 4, 5];
    const players = new Map();

    (daily?.rounds || []).forEach((round) => {
        (round.guesses || []).forEach((guess) => {
            const id = String(guess.playerId);

            if (!players.has(id)) {
                players.set(id, {
                    id: guess.playerId,
                    label: guess.label,
                    color: guess.color,
                    scoresByRound: {},
                });
            }

            players.get(id).scoresByRound[round.number] = guess.score;
        });
    });

    return {
        rounds,
        players: [...players.values()].map((player) => ({
            id: player.id,
            label: player.label,
            color: player.color,
            scores: rounds.map((number) => {
                const score = player.scoresByRound[number];

                return typeof score === 'number' ? score : null;
            }),
        })),
    };
}

export function renderDayOverview(section, payload, charts) {
    if (!section) {
        return;
    }

    const overview = normalizeOverview(payload);
    const hasScores = overview.players.some((player) => player.scores.some((score) => score !== null));

    renderLine(section, overview, charts, hasScores);
    renderBars(section, overview, charts, hasScores);
    renderLegend(section, overview, hasScores);
}

function renderLine(section, overview, charts, hasScores) {
    const canvas = section.querySelector('[data-day-chart="progress"]');
    const empty = section.querySelector('[data-day-empty="progress"]');

    if (!canvas) {
        return;
    }

    canvas.parentElement?.classList.toggle('hidden', !hasScores);
    empty?.classList.toggle('hidden', hasScores);

    if (!hasScores) {
        charts.progress?.destroy();
        charts.progress = null;

        return;
    }

    const labels = overview.rounds.map((number) => `R${number}`);
    const datasets = overview.players.map((player, index) => {
        let running = 0;
        const color = player.color || fallbackColor(index);

        return {
            label: player.label,
            data: player.scores.map((score) => {
                if (score === null) {
                    return null;
                }

                running += score;

                return running;
            }),
            borderColor: color,
            backgroundColor: color,
            tension: 0.2,
            spanGaps: true,
            pointRadius: 3,
            pointHoverRadius: 5,
            borderWidth: 2,
        };
    });

    setChart(charts, 'progress', canvas, {
        type: 'line',
        data: { labels, datasets },
        options: chartOptions({
            suggestedMax: 25000,
            stepSize: 5000,
        }),
    });
}

function renderBars(section, overview, charts, hasScores) {
    const canvas = section.querySelector('[data-day-chart="rounds"]');
    const empty = section.querySelector('[data-day-empty="rounds"]');

    if (!canvas) {
        return;
    }

    canvas.parentElement?.classList.toggle('hidden', !hasScores);
    empty?.classList.toggle('hidden', hasScores);

    if (!hasScores) {
        charts.rounds?.destroy();
        charts.rounds = null;

        return;
    }

    const labels = overview.rounds.map((number) => `R${number}`);
    const datasets = overview.players.map((player, index) => {
        const color = player.color || fallbackColor(index);

        return {
            label: player.label,
            data: player.scores,
            backgroundColor: `${color}CC`,
            borderColor: color,
            borderWidth: 1,
            borderRadius: 6,
        };
    });

    setChart(charts, 'rounds', canvas, {
        type: 'bar',
        data: { labels, datasets },
        options: chartOptions({
            suggestedMax: 5000,
            stepSize: 1000,
        }),
    });
}

function renderLegend(section, overview, hasScores) {
    const host = section.querySelector('[data-day-legend]');

    if (!host) {
        return;
    }

    host.classList.toggle('hidden', !hasScores);

    if (!hasScores) {
        host.replaceChildren();

        return;
    }

    host.innerHTML = overview.players
        .map((player, index) => {
            const color = player.color || fallbackColor(index);

            return `
                <li class="flex items-start gap-2 text-xs leading-snug text-base-content/80">
                    <span class="mt-0.5 h-2.5 w-2.5 shrink-0 rounded-full" style="background:${color}"></span>
                    <span class="min-w-0 flex-1 break-words">${escapeHtml(player.label || 'Unknown')}</span>
                </li>
            `;
        })
        .join('');
}

function setChart(charts, key, canvas, config) {
    charts[key]?.destroy();
    charts[key] = new Chart(canvas, config);
}

function chartOptions({ suggestedMax, stepSize }) {
    return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false,
            },
        },
        scales: {
            x: {
                grid: { display: false },
                ticks: { color: currentTheme().text },
            },
            y: {
                beginAtZero: true,
                suggestedMax,
                grid: { color: currentTheme().grid, drawBorder: false },
                ticks: {
                    color: currentTheme().text,
                    maxTicksLimit: 6,
                    stepSize,
                    callback: (value) => Number(value).toLocaleString(),
                },
            },
        },
    };
}

export function bindTabResize(section, tabName, charts) {
    if (!section) {
        return;
    }

    section.closest('[data-geoguessr-board]')?.querySelectorAll('[data-tab]').forEach((tab) => {
        tab.addEventListener('change', () => {
            if (tab.getAttribute('data-tab') !== tabName || !tab.checked) {
                return;
            }

            requestAnimationFrame(() => {
                charts.progress?.resize();
                charts.rounds?.resize();
            });
        });
    });
}

function parseOverview(raw) {
    try {
        return normalizeOverview(JSON.parse(raw || '{}'));
    } catch {
        return { rounds: [1, 2, 3, 4, 5], players: [] };
    }
}

function normalizeOverview(payload) {
    const rounds = Array.isArray(payload?.rounds) && payload.rounds.length
        ? payload.rounds.map((number) => Number(number)).filter((number) => Number.isFinite(number))
        : [1, 2, 3, 4, 5];

    return {
        rounds,
        players: Array.isArray(payload?.players) ? payload.players : [],
    };
}

function fallbackColor(index) {
    const palette = ['#2A9D8F', '#E85D04', '#283030', '#9B2226', '#FEC523', '#F4A261'];

    return palette[index % palette.length];
}

function currentTheme() {
    if (theme) {
        return theme;
    }

    theme = chartTheme();

    return theme;
}

function chartTheme() {
    const content = cssVarColor('--color-base-content');

    return {
        text: content,
        grid: withAlpha(content, 0.14),
    };
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

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');
}
