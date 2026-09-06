const FULLSCREEN_CLASS = 'challenge-map-fullscreen';
const exits = new WeakMap();
let activeExit = null;

export function bindMapFullscreen(wrap, button, mapOf) {
    if (!(wrap instanceof HTMLElement) || !(button instanceof HTMLButtonElement)) {
        return;
    }

    if (exits.has(wrap)) {
        return;
    }

    const resize = () => {
        requestAnimationFrame(() => mapOf()?.invalidateSize());
    };

    let home = null;

    const exit = () => {
        if (!wrap.classList.contains(FULLSCREEN_CLASS)) {
            return;
        }

        wrap.classList.remove(FULLSCREEN_CLASS);
        document.body.classList.remove('overflow-hidden');
        button.textContent = 'Full screen';

        if (home?.parent) {
            home.parent.insertBefore(wrap, home.next);
        }

        home = null;
        mapOf()?.scrollWheelZoom.disable();
        resize();

        if (activeExit === exit) {
            activeExit = null;
        }
    };

    const enter = () => {
        activeExit?.();

        home = {
            parent: wrap.parentElement,
            next: wrap.nextSibling,
        };
        document.body.appendChild(wrap);
        wrap.classList.add(FULLSCREEN_CLASS);
        document.body.classList.add('overflow-hidden');
        button.textContent = 'Exit';
        mapOf()?.scrollWheelZoom.enable();
        resize();
        activeExit = exit;
    };

    exits.set(wrap, exit);

    button.addEventListener('click', () => {
        if (wrap.classList.contains(FULLSCREEN_CLASS)) {
            exit();
        } else {
            enter();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && wrap.classList.contains(FULLSCREEN_CLASS)) {
            exit();
        }
    });
}

export function exitMapFullscreen(wrap) {
    if (wrap instanceof HTMLElement) {
        exits.get(wrap)?.();
    }
}
