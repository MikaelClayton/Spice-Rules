import Chart from 'chart.js/auto';

export function bindFitIshCharts(board) {
    const todayTab = board.querySelector('[data-tab="today"]');
    const todayPane = todayTab instanceof HTMLElement ? todayTab.nextElementSibling : null;

    if (todayPane instanceof HTMLElement) {
        renderFitIshCharts(todayPane);
    }

    const sessionsTab = board.querySelector('[data-tab="sessions"]');

    if (sessionsTab instanceof HTMLInputElement && sessionsTab.checked) {
        const sessionsPane = sessionsTab.nextElementSibling;

        if (sessionsPane instanceof HTMLElement) {
            renderFitIshCharts(sessionsPane);
        }
    }

    const youTab = board.querySelector('[data-tab="you"]');

    if (youTab instanceof HTMLInputElement && youTab.checked) {
        const youPane = youTab.nextElementSibling;

        if (youPane instanceof HTMLElement) {
            renderFitIshCharts(youPane);
        }
    }

    board.querySelectorAll('[data-tab]').forEach((tab) => {
        tab.addEventListener('change', () => {
            if (!(tab instanceof HTMLInputElement) || !tab.checked) {
                return;
            }

            const pane = tab.nextElementSibling;

            if (!(pane instanceof HTMLElement)) {
                return;
            }

            requestAnimationFrame(() => {
                renderFitIshCharts(pane);
                pane.querySelectorAll('canvas').forEach((canvas) => Chart.getChart(canvas)?.resize());
            });
        });
    });
}

export function renderFitIshCharts(root) {
    root.querySelectorAll('[data-fit-ish-chart]').forEach((node) => {
        if (!(node instanceof HTMLElement)) {
            return;
        }

        const canvas = node.querySelector('canvas');
        const payload = parseJson(node.querySelector('script')?.textContent);
        const type = node.getAttribute('data-fit-ish-chart');

        if (!(canvas instanceof HTMLCanvasElement) || !Array.isArray(payload.datasets) || payload.datasets.length === 0) {
            return;
        }

        Chart.getChart(canvas)?.destroy();

        const config = type === 'radar'
            ? radarConfig(payload)
            : type === 'monthly'
                ? barConfig(payload, false)
                : type === 'zone-stacks'
                    ? barConfig(payload, true)
                    : null;

        if (config) {
            new Chart(canvas, config);
        }
    });
}

function radarConfig(payload) {
    const theme = chartTheme();

    return {
        type: 'radar',
        data: {
            labels: payload.labels || [],
            datasets: payload.datasets.map((dataset) => ({
                label: dataset.label,
                data: dataset.values,
                borderColor: dataset.color,
                backgroundColor: withHexAlpha(dataset.color, 0.18),
                pointBackgroundColor: dataset.color,
                pointBorderColor: dataset.color,
                pointRadius: 2,
                borderWidth: 2,
                fill: true,
            })),
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false,
                },
            },
            scales: {
                r: {
                    min: 0,
                    max: 100,
                    ticks: {
                        display: false,
                    },
                    pointLabels: {
                        color: theme.text,
                        font: {
                            size: window.innerWidth < 640 ? 10 : 12,
                        },
                    },
                    grid: {
                        color: theme.grid,
                    },
                    angleLines: {
                        color: theme.grid,
                    },
                },
            },
        },
    };
}

function barConfig(payload, percent) {
    const theme = chartTheme();

    return {
        type: 'bar',
        data: {
            labels: payload.labels || [],
            datasets: payload.datasets.map((dataset) => ({
                label: dataset.label,
                data: dataset.values,
                backgroundColor: dataset.color,
                borderWidth: 0,
                stack: 'fit-ish',
            })),
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 10,
                        color: theme.text,
                        padding: 10,
                    },
                },
            },
            scales: {
                x: {
                    stacked: true,
                    grid: {
                        display: false,
                    },
                    ticks: {
                        color: theme.text,
                    },
                },
                y: {
                    stacked: true,
                    beginAtZero: true,
                    max: percent ? 100 : undefined,
                    grid: {
                        color: theme.grid,
                    },
                    ticks: {
                        color: theme.text,
                        maxTicksLimit: 6,
                        callback: (value) => (percent ? `${value}%` : value),
                    },
                },
            },
        },
    };
}

function parseJson(raw) {
    try {
        const parsed = JSON.parse(raw || '{}');

        return parsed && typeof parsed === 'object' ? parsed : {};
    } catch {
        return {};
    }
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

function withHexAlpha(hex, alpha) {
    const match = /^#?([0-9A-Fa-f]{6})$/.exec(String(hex || ''));

    if (!match) {
        return hex;
    }

    const value = Number.parseInt(match[1], 16);

    return `rgba(${(value >> 16) & 255}, ${(value >> 8) & 255}, ${value & 255}, ${alpha})`;
}
