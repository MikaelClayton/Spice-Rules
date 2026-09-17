const root = document.querySelector('[data-spirdle-play]');
const gameNode = document.querySelector('[data-spirdle-game]');

if (root instanceof HTMLElement && gameNode) {
    bindSpirdlePlay(root, JSON.parse(gameNode.textContent || '{}'));
}

function bindSpirdlePlay(playRoot, initial) {
    const readonly = playRoot.getAttribute('data-readonly') === 'true';
    const guessUrl = playRoot.getAttribute('data-guess-url') || '';
    const pauseUrl = playRoot.getAttribute('data-pause-url') || '';
    const resumeUrl = playRoot.getAttribute('data-resume-url') || '';
    const wordsUrl = playRoot.getAttribute('data-words-url') || '';
    const boardUrl = playRoot.getAttribute('data-board-url') || '';
    const tiles = [...playRoot.querySelectorAll('[data-tile]')];
    const rows = [...playRoot.querySelectorAll('[data-row]')];
    const keys = new Map(
        [...playRoot.querySelectorAll('[data-key]')].map((key) => [key.getAttribute('data-key'), key]),
    );
    const toastHost = playRoot.querySelector('[data-spirdle-toast]');
    const clock = document.querySelector('[data-spirdle-clock]');
    const state = {
        guesses: Array.isArray(initial.guesses) ? initial.guesses : [],
        row: Array.isArray(initial.guesses) ? initial.guesses.length : 0,
        col: 0,
        current: ['', '', '', '', ''],
        locked: Boolean(initial.finished) || readonly,
        finished: Boolean(initial.finished) || readonly,
        words: null,
        elapsedMs: Number.isFinite(initial.elapsedMs) ? initial.elapsedMs : 0,
        runningSince: Date.now(),
        paused: Boolean(initial.paused),
    };

    paintKeyboard(state.guesses, keys);

    if (!readonly) {
        loadWords(wordsUrl).then((words) => {
            state.words = words;
        });
    }

    layoutSpirdleBoard(playRoot);

    let clockTimer = null;

    if (!readonly && !state.finished) {
        tickClock();
        clockTimer = window.setInterval(tickClock, 250);
        window.addEventListener('visibilitychange', onVisibility);
        window.addEventListener('pagehide', pauseGame);
        window.addEventListener('pageshow', resumeGame);
        window.addEventListener('focus', resumeGame);

        if (state.paused) {
            resumeGame();
        }
    }

    if (readonly) {
        return;
    }

    playRoot.addEventListener('click', (event) => {
        const button = event.target instanceof Element ? event.target.closest('[data-key]') : null;
        const key = button?.getAttribute('data-key');

        if (key) {
            handleKey(key);
        }
    });

    window.addEventListener('keydown', (event) => {
        if (event.metaKey || event.ctrlKey || event.altKey) {
            return;
        }

        if (event.key === 'Enter') {
            event.preventDefault();
            handleKey('Enter');
            return;
        }

        if (event.key === 'Backspace') {
            event.preventDefault();
            handleKey('Backspace');
            return;
        }

        if (/^[a-zA-Z]$/.test(event.key)) {
            event.preventDefault();
            handleKey(event.key.toLowerCase());
        }
    });

    function handleKey(key) {
        if (state.locked || state.finished) {
            return;
        }

        if (key === 'Enter') {
            submitRow();
            return;
        }

        if (key === 'Backspace') {
            deleteLetter();
            return;
        }

        if (key.length === 1 && key >= 'a' && key <= 'z') {
            typeLetter(key);
        }
    }

    function typeLetter(letter) {
        if (state.col >= 5) {
            return;
        }

        const tile = tileAt(state.row, state.col);

        if (!tile) {
            return;
        }

        state.current[state.col] = letter;
        tile.textContent = letter.toUpperCase();
        tile.classList.remove('is-deleting');
        tile.classList.add('is-filled');
        restartAnimation(tile);
        state.col += 1;
    }

    function deleteLetter() {
        if (state.col <= 0) {
            return;
        }

        state.col -= 1;
        state.current[state.col] = '';
        const tile = tileAt(state.row, state.col);

        if (!tile) {
            return;
        }

        tile.textContent = '';
        tile.classList.remove('is-filled');
        tile.classList.add('is-deleting');
        restartAnimation(tile);
    }

    async function submitRow() {
        if (state.col < 5) {
            shake(state.row);
            showToast('5 letters.');
            return;
        }

        const word = state.current.join('');

        if (state.words instanceof Set && !state.words.has(word)) {
            state.locked = true;
            shake(state.row);
            showToast('Not in the word list.');
            await postGuess(word);
            state.locked = false;
            return;
        }

        state.locked = true;
        const result = await postGuess(word);

        if (!result) {
            state.locked = false;
            showToast('Could not submit. Try again.');
            return;
        }

        if (result.status === 'invalid' || result.status === 'repeat') {
            shake(state.row);
            showToast(result.message || 'Not in the word list.');
            state.locked = false;
            return;
        }

        if (result.status === 'missing') {
            showToast(result.message || 'Open the board first.');
            state.locked = false;
            return;
        }

        const latest = result.play?.guesses?.at(-1);

        if (!latest?.tiles) {
            state.locked = false;
            return;
        }

        await reveal(state.row, latest.word, latest.tiles);
        paintKeyboard(result.play.guesses || [], keys);
        state.guesses = result.play.guesses || [];
        state.current = ['', '', '', '', ''];
        state.col = 0;
        state.row += 1;
        syncTimer(result.play);

        if (result.play.finished) {
            state.finished = true;
            state.locked = true;
            finish(result.play);
            return;
        }

        state.locked = false;
    }

    async function postGuess(word) {
        try {
            const response = await fetch(guessUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: jsonHeaders(),
                body: JSON.stringify({ word }),
            });

            return await response.json();
        } catch {
            return null;
        }
    }

    function onVisibility() {
        if (document.visibilityState === 'hidden') {
            pauseGame();
            return;
        }

        resumeGame();
    }

    function pauseGame() {
        if (state.finished || state.paused) {
            return;
        }

        state.elapsedMs = displayedElapsed();
        state.paused = true;
        ping(pauseUrl);
        tickClock();
    }

    async function resumeGame() {
        if (state.finished || !state.paused) {
            return;
        }

        const result = await ping(resumeUrl);
        syncTimer(result?.play || { elapsedMs: state.elapsedMs, paused: false });
        tickClock();
    }

    function ping(url) {
        if (!url) {
            return Promise.resolve(null);
        }

        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            keepalive: true,
            headers: jsonHeaders(),
            body: '{}',
        })
            .then((response) => response.json())
            .catch(() => null);
    }

    function syncTimer(play) {
        if (!play || typeof play !== 'object') {
            return;
        }

        if (Number.isFinite(play.elapsedMs)) {
            state.elapsedMs = play.elapsedMs;
        }

        state.paused = Boolean(play.paused);
        state.runningSince = Date.now();
    }

    function displayedElapsed() {
        if (state.paused || state.finished) {
            return state.elapsedMs;
        }

        return state.elapsedMs + Math.max(0, Date.now() - state.runningSince);
    }

    function reveal(row, word, tileStates) {
        const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const stagger = reduced ? 0 : 110;
        const pop = reduced ? 0 : 280;

        return new Promise((resolve) => {
            for (let col = 0; col < 5; col += 1) {
                const tile = tileAt(row, col);

                if (!tile) {
                    continue;
                }

                window.setTimeout(() => {
                    tile.textContent = (word[col] || '').toUpperCase();
                    applyTile(tile, tileStates[col]);
                    tile.classList.add('is-filled', 'is-revealing');
                    restartAnimation(tile);
                    window.setTimeout(() => {
                        tile.classList.remove('is-revealing');
                        if (col === 4) {
                            resolve();
                        }
                    }, pop);
                }, col * stagger);
            }
        });
    }

    function finish(play) {
        state.paused = true;
        state.elapsedMs = Number.isFinite(play.durationMs) ? play.durationMs : displayedElapsed();

        if (clockTimer) {
            window.clearInterval(clockTimer);
        }

        if (clock instanceof HTMLElement && play.durationLabel) {
            clock.textContent = play.durationLabel;
        }

        const board = playRoot.querySelector('[data-board]');

        if (play.won) {
            board?.classList.add('is-won');
            burstFinish(true, board);
        } else {
            board?.classList.add('is-lost');
            burstFinish(false, board);
        }

        window.setTimeout(() => {
            window.location.href = boardUrl;
        }, 1500);
    }

    function shake(row) {
        const node = rows[row];

        if (!(node instanceof HTMLElement)) {
            return;
        }

        node.classList.remove('is-invalid');
        restartAnimation(node);
        node.classList.add('is-invalid');
        window.setTimeout(() => node.classList.remove('is-invalid'), 420);
    }

    function tileAt(row, col) {
        return tiles[row * 5 + col] || null;
    }

    function showToast(message) {
        if (!(toastHost instanceof HTMLElement) || message === '') {
            return;
        }

        toastHost.replaceChildren();
        const alert = document.createElement('div');
        alert.setAttribute('role', 'status');
        alert.className = 'alert shadow-lg';
        const text = document.createElement('span');
        text.textContent = message;
        alert.append(text);
        toastHost.append(alert);
        window.setTimeout(() => alert.remove(), 2200);
    }

    function tickClock() {
        if (!(clock instanceof HTMLElement)) {
            return;
        }

        clock.textContent = formatDuration(displayedElapsed());
    }
}

function jsonHeaders() {
    return {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        'X-Requested-With': 'XMLHttpRequest',
    };
}

function applyTile(tile, kind) {
    tile.classList.remove('is-correct', 'is-present', 'is-absent');

    if (kind === 'correct' || kind === 'present' || kind === 'absent') {
        tile.classList.add(`is-${kind}`);
    }
}

function paintKeyboard(guesses, keys) {
    const rank = { correct: 3, present: 2, absent: 1 };
    const best = {};

    guesses.forEach((guess) => {
        const word = guess.word || '';
        (guess.tiles || []).forEach((kind, index) => {
            const letter = word[index];

            if (!letter) {
                return;
            }

            if ((rank[kind] || 0) > (rank[best[letter]] || 0)) {
                best[letter] = kind;
            }
        });
    });

    Object.entries(best).forEach(([letter, kind]) => {
        const key = keys.get(letter);

        if (key instanceof HTMLElement) {
            applyTile(key, kind);
        }
    });
}

function formatDuration(milliseconds) {
    const seconds = Math.floor(Math.max(0, milliseconds) / 1000);

    if (seconds < 60) {
        return `${seconds}s`;
    }

    const minutes = Math.floor(seconds / 60);
    const remainder = String(seconds % 60).padStart(2, '0');

    return `${minutes}:${remainder}`;
}

function burstFinish(won, origin) {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    const canvas = document.createElement('canvas');
    canvas.className = 'spirdle-burst';
    canvas.setAttribute('aria-hidden', 'true');
    document.body.append(canvas);

    const ctx = canvas.getContext('2d');

    if (!ctx) {
        canvas.remove();
        return;
    }

    const resize = () => {
        const dpr = window.devicePixelRatio || 1;
        canvas.width = Math.floor(window.innerWidth * dpr);
        canvas.height = Math.floor(window.innerHeight * dpr);
        canvas.style.width = `${window.innerWidth}px`;
        canvas.style.height = `${window.innerHeight}px`;
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    };

    resize();

    const rect =
        origin instanceof HTMLElement
            ? origin.getBoundingClientRect()
            : { left: window.innerWidth / 2, top: window.innerHeight / 3, width: 0, height: 0 };
    const colors = won
        ? ['#d82820', '#fec523', '#2f7d4a', '#fffdf6', '#ead9b4']
        : ['#283030', '#4a3333', '#6b2420', '#1c1c1c', '#5c5348'];
    const pieces = [];
    const count = won ? 96 : 64;

    for (let index = 0; index < count; index += 1) {
        const fromLeft = index % 2 === 0;
        const x = won
            ? fromLeft
                ? rect.left + 8
                : rect.left + rect.width - 8
            : rect.left + Math.random() * Math.max(rect.width, 40);
        const y = won ? rect.bottom - 8 : rect.top;
        const angle = won
            ? fromLeft
                ? -Math.PI / 2.4 - Math.random() * 0.7
                : -Math.PI / 1.7 + Math.random() * 0.7
            : Math.PI / 2 + (Math.random() - 0.5) * 0.6;
        const speed = won ? 7 + Math.random() * 9 : 1.5 + Math.random() * 3.5;

        pieces.push({
            x,
            y,
            vx: Math.cos(angle) * speed,
            vy: Math.sin(angle) * speed,
            w: won ? 6 + Math.random() * 7 : 3 + Math.random() * 5,
            h: won ? 9 + Math.random() * 8 : 12 + Math.random() * 18,
            rot: Math.random() * Math.PI,
            vr: (Math.random() - 0.5) * (won ? 0.35 : 0.12),
            color: colors[index % colors.length],
            gravity: won ? 0.17 : 0.32,
        });
    }

    if (!won) {
        const flash = document.createElement('div');
        flash.className = 'spirdle-fail-flash';
        flash.setAttribute('aria-hidden', 'true');
        document.body.append(flash);
        window.setTimeout(() => flash.remove(), 900);
    }

    const started = performance.now();

    function frame(now) {
        ctx.clearRect(0, 0, window.innerWidth, window.innerHeight);

        pieces.forEach((piece) => {
            piece.vy += piece.gravity;
            piece.vx *= 0.992;
            piece.x += piece.vx;
            piece.y += piece.vy;
            piece.rot += piece.vr;
            ctx.save();
            ctx.translate(piece.x, piece.y);
            ctx.rotate(piece.rot);
            ctx.globalAlpha = won ? 0.95 : 0.8;
            ctx.fillStyle = piece.color;
            ctx.fillRect(-piece.w / 2, -piece.h / 2, piece.w, piece.h);
            ctx.restore();
        });

        if (now - started < 2500) {
            requestAnimationFrame(frame);
            return;
        }

        canvas.remove();
    }

    requestAnimationFrame(frame);
}

function restartAnimation(node) {
    node.style.animation = 'none';
    node.offsetHeight;
    node.style.animation = '';
}

function layoutSpirdleBoard(playRoot) {
    const board = playRoot.querySelector('[data-board]');
    const keyboard = playRoot.querySelector('[data-keyboard]');
    const hasKeyboard = keyboard instanceof HTMLElement;

    if (!(board instanceof HTMLElement)) {
        return;
    }

    const boardGap = 5;
    const keyMin = 48;
    const keyMax = 62;
    const minTile = 28;

    const stackGap = () => Number.parseFloat(getComputedStyle(playRoot).rowGap) || 8;

    const visiblePlayHeight = () => {
        const rect = playRoot.getBoundingClientRect();
        const viewport = window.visualViewport;
        const visibleBottom = viewport ? viewport.offsetTop + viewport.height : window.innerHeight;
        const slack = 8;

        return Math.max(0, Math.floor(Math.min(playRoot.clientHeight, visibleBottom - rect.top) - slack));
    };

    const applyBoard = (tileWidth, tileHeight) => {
        board.style.width = `${tileWidth * 5 + boardGap * 4}px`;
        board.style.height = 'auto';
        board.style.gap = `${boardGap}px`;
        board.style.gridTemplateRows = `repeat(6, ${tileHeight}px)`;
        playRoot.style.setProperty('--spirdle-tile-font', `${Math.floor(Math.min(tileWidth, tileHeight) * 0.45)}px`);
    };

    const sizePlay = () => {
        const playWidth = playRoot.clientWidth;
        const playHeight = visiblePlayHeight();

        if (playWidth < 80 || playHeight < 80) {
            return;
        }

        if (hasKeyboard) {
            playRoot.style.setProperty('--spirdle-key-height', `${keyMin}px`);
        }

        const minKeyBlock = hasKeyboard ? keyboard.offsetHeight : 0;
        const gap = stackGap();
        const tileFromWidth = Math.max(minTile, Math.floor((playWidth - boardGap * 4) / 5));
        const tileFromHeight = Math.max(
            minTile,
            Math.floor((playHeight - gap - minKeyBlock - boardGap * 5) / 6),
        );
        const tileWidth = Math.min(tileFromWidth, tileFromHeight);
        let tileHeight = tileWidth;

        applyBoard(tileWidth, tileHeight);

        const leftoverAfterBoard = playHeight - gap - board.offsetHeight - minKeyBlock;

        if (hasKeyboard) {
            const keyHeight = Math.max(keyMin, Math.min(keyMax, keyMin + Math.floor(leftoverAfterBoard / 3)));
            playRoot.style.setProperty('--spirdle-key-height', `${keyHeight}px`);
        }

        const leftoverAfterKeys = playHeight - gap - board.offsetHeight - (hasKeyboard ? keyboard.offsetHeight : 0);

        if (leftoverAfterKeys >= 6) {
            tileHeight += Math.floor(leftoverAfterKeys / 6);
            applyBoard(tileWidth, tileHeight);
        }

        const overflow = gap + board.offsetHeight + (hasKeyboard ? keyboard.offsetHeight : 0) - playHeight;

        if (overflow > 0) {
            tileHeight = Math.max(minTile, tileHeight - Math.ceil(overflow / 6));
            applyBoard(tileWidth, tileHeight);
        }
    };

    sizePlay();
    requestAnimationFrame(sizePlay);

    if (typeof ResizeObserver === 'function') {
        const observer = new ResizeObserver(sizePlay);
        observer.observe(playRoot);
    }

    window.addEventListener('resize', sizePlay);
    window.visualViewport?.addEventListener('resize', sizePlay);
}

async function loadWords(url) {
    if (!url) {
        return null;
    }

    try {
        const response = await fetch(url, { credentials: 'same-origin' });

        if (!response.ok) {
            return null;
        }

        const text = await response.text();

        return new Set(
            text
                .split('\n')
                .map((word) => word.trim().toLowerCase())
                .filter((word) => word.length === 5),
        );
    } catch {
        return null;
    }
}
