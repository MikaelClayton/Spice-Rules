import Chart from 'chart.js/auto';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { setButtonLoading } from './button-loading';
import { bindMapFullscreen } from './geoguessr-map-fullscreen';
import { renderSVG } from './lib/uqr.mjs';

bindDrinkPicker(document.querySelector('[data-pub-golf-board]'));
bindDrinkConfirmations(document.querySelector('[data-pub-golf-board]'));
bindAddDrinkPhoto(document.querySelector('[data-add-drink]'));
bindCopyButtons();
bindDuration(document.querySelector('[data-pub-golf-board]'));
bindPubGolfChat(document.querySelector('[data-pub-golf-chat]'));
bindPubGolfLocationSettings(document.querySelector('[data-pub-golf-location]'));
bindPubGolfMaps(document.querySelectorAll('[data-pub-golf-map-wrap]'));
renderRecap(document.querySelector('[data-pub-golf-recap]'));
bindCalorieBurn(document.querySelector('[data-calorie-burn]'));
bindJoinQr(document.querySelector('[data-join-qr]'));
bindJoinScanner(document.querySelector('[data-join-scan]'));

function bindJoinQr(dialog) {
    if (!(dialog instanceof HTMLDialogElement)) {
        return;
    }

    const openButtons = document.querySelectorAll('[data-join-qr-open]');
    const target = dialog.querySelector('[data-join-qr-target]');
    const joinUrl = dialog.getAttribute('data-join-url') || '';

    if (!(target instanceof HTMLElement) || joinUrl === '') {
        return;
    }

    const render = () => {
        if (target.dataset.ready === '1') {
            return;
        }

        try {
            target.innerHTML = renderSVG(joinUrl, {
                pixelSize: 6,
                whiteColor: '#ffffff',
                blackColor: '#111111',
                border: 1,
            });
            const svg = target.querySelector('svg');

            if (svg instanceof SVGElement) {
                svg.setAttribute('class', 'h-full w-full');
                svg.setAttribute('aria-label', 'QR code to join this crawl');
            }

            target.dataset.ready = '1';
        } catch {
            target.innerHTML = '<p class="text-sm text-error">Could not draw the QR code.</p>';
        }
    };

    openButtons.forEach((button) => {
        button.addEventListener('click', () => {
            render();
            dialog.showModal();
        });
    });
}

function bindJoinScanner(dialog) {
    if (!(dialog instanceof HTMLDialogElement)) {
        return;
    }

    const openButton = document.querySelector('[data-join-scan-open]');
    const form = document.querySelector('[data-join-form]');
    const codeInput = document.querySelector('[data-join-code-input]');
    const video = dialog.querySelector('[data-join-scan-video]');
    const status = dialog.querySelector('[data-join-scan-status]');
    const closeButtons = dialog.querySelectorAll('[data-join-scan-close]');

    if (
        !(openButton instanceof HTMLButtonElement)
        || !(form instanceof HTMLFormElement)
        || !(codeInput instanceof HTMLInputElement)
        || !(video instanceof HTMLVideoElement)
        || !(status instanceof HTMLElement)
    ) {
        return;
    }

    let stream = null;
    let detector = null;
    let rafId = 0;
    let handling = false;

    const stop = () => {
        handling = false;

        if (rafId) {
            window.cancelAnimationFrame(rafId);
            rafId = 0;
        }

        if (stream) {
            stream.getTracks().forEach((track) => track.stop());
            stream = null;
        }

        video.srcObject = null;
    };

    const setStatus = (message) => {
        status.textContent = message;
    };

    const applyCode = (code) => {
        if (handling) {
            return;
        }

        handling = true;
        codeInput.value = code;
        stop();
        dialog.close();
        form.requestSubmit();
    };

    const tick = async () => {
        if (!detector || video.readyState < 2 || handling) {
            rafId = window.requestAnimationFrame(tick);

            return;
        }

        try {
            const codes = await detector.detect(video);

            for (const code of codes) {
                const joinCode = extractJoinCode(code.rawValue || '');

                if (joinCode) {
                    applyCode(joinCode);

                    return;
                }
            }
        } catch {
            // Keep scanning; some frames fail while the camera settles.
        }

        rafId = window.requestAnimationFrame(tick);
    };

    const start = async () => {
        stop();
        setStatus('Starting camera…');
        dialog.showModal();

        if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
            setStatus('Camera needs HTTPS on this device. Type the code instead.');

            return;
        }

        if (typeof BarcodeDetector === 'undefined') {
            setStatus('QR scanning is not supported in this browser. Type the code instead.');

            return;
        }

        try {
            detector = new BarcodeDetector({ formats: ['qr_code'] });
            stream = await navigator.mediaDevices.getUserMedia({
                audio: false,
                video: {
                    facingMode: { ideal: 'environment' },
                },
            });
            video.srcObject = stream;
            await video.play();
            setStatus('Point at the QR code…');
            rafId = window.requestAnimationFrame(tick);
        } catch {
            stop();
            setStatus('Could not open the camera. Check permissions, or type the code.');
        }
    };

    openButton.addEventListener('click', () => {
        void start();
    });

    closeButtons.forEach((button) => {
        button.addEventListener('click', stop);
    });

    dialog.addEventListener('close', stop);
}

function extractJoinCode(raw) {
    const text = String(raw || '').trim();

    if (text === '') {
        return null;
    }

    try {
        const url = new URL(text);
        const match = url.pathname.match(/\/pub-golf\/join\/([A-Za-z0-9]{6})\/?$/i);

        if (match) {
            return match[1].toUpperCase();
        }
    } catch {
        // Not a URL — fall through to a bare code.
    }

    const pathMatch = text.match(/\/pub-golf\/join\/([A-Za-z0-9]{6})\/?/i);

    if (pathMatch) {
        return pathMatch[1].toUpperCase();
    }

    const cleaned = text.replace(/[^A-Za-z0-9]/g, '').toUpperCase();

    return cleaned.length === 6 ? cleaned : null;
}

function bindCalorieBurn(root) {
    if (!(root instanceof HTMLElement)) {
        return;
    }

    const calories = Number(root.dataset.calories || 0);
    const met = Number(root.dataset.met || 7);
    const weightInput = root.querySelector('[data-calorie-burn-weight]');
    const minutesNode = root.querySelector('[data-calorie-burn-minutes]');
    const summaryNode = root.querySelector('[data-calorie-burn-summary]');
    const equationNode = root.querySelector('[data-calorie-burn-equation]');

    if (
        !(weightInput instanceof HTMLInputElement)
        || !(minutesNode instanceof HTMLElement)
        || !(summaryNode instanceof HTMLElement)
        || !(equationNode instanceof HTMLElement)
        || !Number.isFinite(calories)
        || calories < 1
        || !Number.isFinite(met)
        || met <= 0
    ) {
        return;
    }

    const update = () => {
        const weightKg = clampNumber(Number(weightInput.value), 40, 200, 70);
        const kcalPerMinute = Math.round(((met * weightKg) / 60) * 100) / 100;
        const minutes = Math.max(1, Math.round(calories / kcalPerMinute));
        const caloriesLabel = formatCount(calories);
        const metLabel = formatMet(met);

        minutesNode.textContent = formatCount(minutes);
        summaryNode.textContent = `About ${formatCount(minutes)} minutes of zone 2 running at ${weightKg} kg (${metLabel} MET).`;
        equationNode.textContent = `${caloriesLabel} ÷ (${metLabel} × ${weightKg} ÷ 60) = ${caloriesLabel} ÷ ${kcalPerMinute.toFixed(2)} ≈ ${minutes} minutes`;
    };

    weightInput.addEventListener('input', update);
    weightInput.addEventListener('change', update);
}

function clampNumber(value, min, max, fallback) {
    if (!Number.isFinite(value)) {
        return fallback;
    }

    return Math.min(max, Math.max(min, value));
}

function formatCount(value) {
    return Number(value).toLocaleString('en-US');
}

function formatMet(met) {
    return String(Number(met.toFixed(1))).replace(/\.0$/, '');
}

function bindPubGolfMaps(wraps) {
    wraps.forEach((wrap) => {
        if (!(wrap instanceof HTMLElement)) {
            return;
        }

        const mount = wrap.querySelector('[data-pub-golf-map]');
        const dataNode = wrap.querySelector('[data-pub-golf-map-pins]');

        if (!(mount instanceof HTMLElement) || !dataNode) {
            return;
        }

        let pins = [];

        try {
            pins = JSON.parse(dataNode.textContent || '[]');
        } catch {
            return;
        }

        if (!Array.isArray(pins) || pins.length === 0) {
            return;
        }

        const map = L.map(mount, { scrollWheelZoom: false });
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap',
            maxZoom: 18,
        }).addTo(map);

        const group = L.layerGroup();
        const bounds = [];
        const byUser = new Map();

        pins.forEach((pin) => {
            const lat = Number(pin.lat);
            const lng = Number(pin.lng);

            if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
                return;
            }

            const point = [lat, lng];
            const color = /^#[0-9A-Fa-f]{6}$/.test(pin.color) ? pin.color : '#D82820';
            const userId = String(pin.user_id ?? 'me');

            bounds.push(point);

            if (!byUser.has(userId)) {
                byUser.set(userId, { color, points: [] });
            }

            byUser.get(userId).points.push(point);
            pubGolfMarker(point, color, pin.initials, pubGolfPinPopup(pin)).addTo(group);
        });

        byUser.forEach((path) => {
            if (path.points.length > 1) {
                L.polyline(path.points, {
                    color: path.color,
                    weight: 3,
                    opacity: 0.75,
                }).addTo(group);
            }
        });

        group.addTo(map);
        bindMapFullscreen(wrap, wrap.querySelector('[data-map-fullscreen]'), () => map);

        requestAnimationFrame(() => {
            map.invalidateSize();

            if (bounds.length === 1) {
                map.setView(bounds[0], 16);
            } else if (bounds.length) {
                map.fitBounds(bounds, { padding: [28, 28], maxZoom: 16 });
            }
        });
    });
}

function pubGolfMarker(latlng, color, label, title) {
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

function pubGolfPinPopup(pin) {
    return [
        pin.name,
        [pin.emoji, pin.label].filter(Boolean).join(' '),
        pin.location,
        pin.crawl,
        pin.time,
    ]
        .filter((part) => typeof part === 'string' && part !== '')
        .map((part) => escapeHtml(part))
        .join(' · ');
}

function bindDrinkPicker(root) {
    if (!(root instanceof HTMLElement)) {
        return;
    }

    const search = root.querySelector('[data-drink-search]');
    const empty = root.querySelector('[data-drink-empty]');
    let category = '';

    const apply = () => {
        const query = search instanceof HTMLInputElement ? search.value.trim().toLowerCase() : '';
        let visible = 0;

        root.querySelectorAll('[data-drink-tile]').forEach((tile) => {
            if (!(tile instanceof HTMLElement)) {
                return;
            }

            const name = tile.getAttribute('data-drink-name') || '';
            const drinkCategory = tile.getAttribute('data-drink-category') || '';
            const matchesCategory = category === '' || drinkCategory === category;
            const matchesQuery = query === '' || name.includes(query);
            const show = matchesCategory && matchesQuery;

            tile.classList.toggle('hidden', !show);
            if (show) {
                visible += 1;
            }
        });

        empty?.classList.toggle('hidden', visible > 0);
    };

    search?.addEventListener('input', apply);

    root.querySelectorAll('[data-drink-filter]').forEach((button) => {
        button.addEventListener('click', () => {
            category = button.getAttribute('data-drink-filter') || '';

            root.querySelectorAll('[data-drink-filter]').forEach((other) => {
                const active = other === button;
                other.classList.toggle('btn-primary', active);
                other.classList.toggle('btn-ghost', !active);
                other.setAttribute('aria-pressed', active ? 'true' : 'false');
            });

            apply();
        });
    });
}

function bindDrinkConfirmations(root) {
    if (!(root instanceof HTMLElement)) {
        return;
    }

    const logModal = document.getElementById('pub-golf-log');
    const removeModal = document.getElementById('pub-golf-remove');
    const logForm = logModal?.querySelector('[data-log-form]');
    const latitudeNode = logForm?.querySelector('[data-log-latitude]');
    const longitudeNode = logForm?.querySelector('[data-log-longitude]');
    const denyUrl = root.getAttribute('data-location-deny-url') || '';
    let allowLocation = root.getAttribute('data-allow-location') === '1';
    let locationPromise = Promise.resolve();
    let locationPending = false;
    const locationCacheMs = 30 * 60 * 1000;
    const locationCacheKey = 'pub-golf-coords';

    const fillCoordinates = (latitude, longitude) => {
        if (latitudeNode instanceof HTMLInputElement) {
            latitudeNode.value = latitude;
        }

        if (longitudeNode instanceof HTMLInputElement) {
            longitudeNode.value = longitude;
        }
    };

    const readCachedCoordinates = () => {
        try {
            const raw = window.sessionStorage.getItem(locationCacheKey);

            if (!raw) {
                return null;
            }

            const cached = JSON.parse(raw);

            if (
                typeof cached?.latitude !== 'number'
                || typeof cached?.longitude !== 'number'
                || typeof cached?.at !== 'number'
                || Date.now() - cached.at > locationCacheMs
            ) {
                return null;
            }

            return cached;
        } catch {
            return null;
        }
    };

    const writeCachedCoordinates = (latitude, longitude) => {
        try {
            window.sessionStorage.setItem(locationCacheKey, JSON.stringify({
                latitude,
                longitude,
                at: Date.now(),
            }));
        } catch {
            // Private mode can block sessionStorage.
        }
    };

    const clearCachedCoordinates = () => {
        try {
            window.sessionStorage.removeItem(locationCacheKey);
        } catch {
            // Ignore storage failures.
        }
    };

    const rememberLocationDenied = () => {
        allowLocation = false;
        root.setAttribute('data-allow-location', '0');
        fillCoordinates('', '');
        clearCachedCoordinates();

        if (denyUrl === '') {
            return;
        }

        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        fetch(denyUrl, {
            method: 'PATCH',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ allow_pub_golf_location: false }),
        }).catch(() => {});
    };

    const captureLocation = ({ force = false } = {}) => {
        if (!allowLocation || !navigator.geolocation) {
            locationPending = false;
            locationPromise = Promise.resolve();
            fillCoordinates('', '');

            return;
        }

        if (!force) {
            const cached = readCachedCoordinates();

            if (cached) {
                fillCoordinates(String(cached.latitude), String(cached.longitude));
                locationPending = false;
                locationPromise = Promise.resolve();

                return;
            }
        }

        locationPending = true;
        locationPromise = new Promise((resolve) => {
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const latitude = position.coords.latitude;
                    const longitude = position.coords.longitude;
                    fillCoordinates(String(latitude), String(longitude));
                    writeCachedCoordinates(latitude, longitude);
                    locationPending = false;
                    resolve();
                },
                (error) => {
                    if (error?.code === 1) {
                        rememberLocationDenied();
                    }

                    locationPending = false;
                    resolve();
                },
                { enableHighAccuracy: false, timeout: 4000, maximumAge: locationCacheMs },
            );
        });
    };

    captureLocation();

    root.querySelectorAll('[data-confirm-log]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!(logModal instanceof HTMLDialogElement)) {
                return;
            }

            const name = button.getAttribute('data-drink-label') || '';
            const key = button.getAttribute('data-drink-key') || '';
            const photo = button.getAttribute('data-drink-photo') || '';
            const calories = button.getAttribute('data-drink-calories') || '';
            const nameNode = logModal.querySelector('[data-confirm-log-name]');
            const valueNode = logModal.querySelector('[data-confirm-log-value]');
            const photoNode = logModal.querySelector('[data-confirm-log-photo]');
            const caloriesNode = logModal.querySelector('[data-log-calories]');

            if (nameNode instanceof HTMLElement) {
                nameNode.textContent = name;
            }

            if (valueNode instanceof HTMLInputElement) {
                valueNode.value = key;
            }

            if (photoNode instanceof HTMLImageElement) {
                photoNode.src = photo;
                photoNode.classList.toggle('hidden', photo === '');
            }

            if (caloriesNode instanceof HTMLInputElement) {
                caloriesNode.value = calories;
            }

            captureLocation();
            logModal.showModal();
        });
    });

    if (logForm instanceof HTMLFormElement) {
        logForm.addEventListener('submit', async (event) => {
            event.preventDefault();

            const button = event.submitter instanceof HTMLButtonElement
                ? event.submitter
                : document.querySelector('button[type="submit"][form="pub-golf-log-form"]');
            setButtonLoading(button, true);

            if (locationPending) {
                await Promise.race([
                    locationPromise,
                    new Promise((resolve) => {
                        window.setTimeout(resolve, 300);
                    }),
                ]);
            }

            try {
                const response = await fetch(logForm.action, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: new FormData(logForm),
                });

                if (!response.ok) {
                    window.location.assign(window.location.href);

                    return;
                }

                const payload = await response.json().catch(() => null);
                window.location.assign(payload?.redirect || window.location.href);
            } catch {
                setButtonLoading(button, false);
            }
        });
    }

    root.querySelectorAll('[data-confirm-remove]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.stopPropagation();

            if (!(removeModal instanceof HTMLDialogElement)) {
                return;
            }

            const name = button.getAttribute('data-drink-label') || '';
            const action = button.getAttribute('data-remove-action') || '';
            const nameNode = removeModal.querySelector('[data-confirm-remove-name]');
            const form = removeModal.querySelector('[data-confirm-remove-form]');

            if (nameNode instanceof HTMLElement) {
                nameNode.textContent = name;
            }

            if (form instanceof HTMLFormElement) {
                form.action = action;
            }

            removeModal.showModal();
        });
    });
}

function bindPubGolfLocationSettings(root) {
    if (!(root instanceof HTMLElement)) {
        return;
    }

    const toggle = root.querySelector('#allow_pub_golf_location');
    const status = root.querySelector('[data-pub-golf-location-status]');
    const save = root.querySelector('[data-pub-golf-location-save]');
    const url = root.getAttribute('data-save-url') || '';
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    if (!(toggle instanceof HTMLInputElement) || url === '') {
        return;
    }

    save?.classList.add('hidden');

    const setStatus = (message, isError = false) => {
        if (!(status instanceof HTMLElement)) {
            return;
        }

        status.textContent = message;
        status.classList.toggle('hidden', message === '');
        status.classList.toggle('text-error', isError);
        status.classList.toggle('text-success', !isError && message !== '');
    };

    const saveFlag = async (allowed) => {
        const response = await fetch(url, {
            method: 'PATCH',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ allow_pub_golf_location: allowed }),
        });

        if (!response.ok) {
            throw new Error('Could not save location settings.');
        }
    };

    const permissionState = async () => {
        if (!navigator.permissions?.query) {
            return 'prompt';
        }

        try {
            const result = await navigator.permissions.query({ name: 'geolocation' });

            return result.state;
        } catch {
            return 'prompt';
        }
    };

    const askForPermission = () => new Promise((resolve, reject) => {
        if (!navigator.geolocation) {
            reject(new Error('This phone cannot share location.'));

            return;
        }

        navigator.geolocation.getCurrentPosition(
            () => resolve(),
            (error) => reject(error),
            { enableHighAccuracy: false, timeout: 8000, maximumAge: Infinity },
        );
    });

    toggle.addEventListener('change', async () => {
        if (!toggle.checked) {
            try {
                await saveFlag(false);

                try {
                    window.sessionStorage.removeItem('pub-golf-coords');
                } catch {
                    // Ignore storage failures.
                }

                setStatus('Location is off. We will not ask your phone.');
            } catch {
                toggle.checked = true;
                setStatus('Could not save location settings.', true);
            }

            return;
        }

        toggle.disabled = true;
        setStatus('Asking your phone…');

        try {
            const state = await permissionState();

            if (state === 'denied') {
                const denied = new Error('denied');
                denied.code = 1;
                throw denied;
            }

            if (state !== 'granted') {
                await askForPermission();
            }

            await saveFlag(true);
            setStatus('Location is on. Choose “Allow” on your phone so we only ask once, then reuse the pin while you log drinks.');
        } catch (error) {
            toggle.checked = false;

            if (error?.code === 1) {
                try {
                    await saveFlag(false);
                } catch {
                    // The toggle is already off.
                }
                setStatus('Location stays off because the phone prompt was refused. We will not ask again.', true);
            } else {
                setStatus(error?.message || 'Could not get location permission. Try again.', true);
            }
        } finally {
            toggle.disabled = false;
        }
    });
}

function bindAddDrinkPhoto(form) {
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    const input = form.querySelector('[data-drink-photo]');
    const error = form.querySelector('[data-drink-photo-error]');

    if (!(input instanceof HTMLInputElement)) {
        return;
    }

    form.addEventListener('submit', async (event) => {
        if (form.dataset.photoReady === '1') {
            return;
        }

        const file = input.files?.[0];

        if (!file) {
            event.preventDefault();
            showDrinkPhotoError(error, 'Add a photo of the drink.');
            return;
        }

        if (isHeicPhoto(file)) {
            event.preventDefault();
            input.value = '';
            showDrinkPhotoError(error, 'Use a JPEG, PNG, or WebP photo. HEIC is not allowed.');
            return;
        }

        event.preventDefault();

        try {
            const compressed = await compressPubGolfPhoto(file);
            const transfer = new DataTransfer();
            transfer.items.add(compressed);
            input.files = transfer.files;
            form.dataset.photoReady = '1';
            form.submit();
        } catch {
            showDrinkPhotoError(error, 'That photo could not be read. Try a JPEG, PNG, or WebP.');
        }
    });
}

function isHeicPhoto(file) {
    const name = file.name.toLowerCase();
    const type = (file.type || '').toLowerCase();

    return name.endsWith('.heic') || name.endsWith('.heif') || type.includes('heic') || type.includes('heif');
}

async function compressPubGolfPhoto(file) {
    const bitmap = await createImageBitmap(file);
    const maxEdge = 1280;
    const scale = Math.min(1, maxEdge / Math.max(bitmap.width, bitmap.height));
    const canvas = document.createElement('canvas');
    canvas.width = Math.max(1, Math.round(bitmap.width * scale));
    canvas.height = Math.max(1, Math.round(bitmap.height * scale));
    const context = canvas.getContext('2d');

    if (!context) {
        throw new Error('Could not compress the photo.');
    }

    context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);

    if (typeof bitmap.close === 'function') {
        bitmap.close();
    }

    const blob = await new Promise((resolve, reject) => {
        canvas.toBlob((result) => {
            if (result) {
                resolve(result);
            } else {
                reject(new Error('Could not compress the photo.'));
            }
        }, 'image/jpeg', 0.78);
    });

    return new File([blob], 'photo.jpg', { type: 'image/jpeg', lastModified: Date.now() });
}

function showDrinkPhotoError(node, message) {
    if (!(node instanceof HTMLElement)) {
        return;
    }

    node.textContent = message;
    node.classList.remove('hidden');
}

function bindCopyButtons() {
    document.querySelectorAll('[data-copy]').forEach((button) => {
        button.addEventListener('click', async () => {
            const value = button.getAttribute('data-copy');

            if (!value || !navigator.clipboard) {
                return;
            }

            try {
                await navigator.clipboard.writeText(value);
            } catch {
                return;
            }
            const original = button.textContent;
            button.textContent = 'Copied ' + value;
            window.setTimeout(() => {
                button.textContent = original;
            }, 1600);
        });
    });
}

function bindDuration(root) {
    const label = root?.querySelector('[data-duration]');
    const startedAt = root?.getAttribute('data-joined-at');

    if (!(label instanceof HTMLElement) || !startedAt) {
        return;
    }

    const started = Date.parse(startedAt);

    if (Number.isNaN(started)) {
        return;
    }

    const tick = () => {
        label.textContent = formatDuration(Math.max(0, Math.floor((Date.now() - started) / 1000)));
    };

    tick();
    window.setInterval(tick, 1000);
}

function formatDuration(seconds) {
    if (seconds < 60) {
        return seconds + 's';
    }

    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);

    if (hours > 0) {
        return hours + 'h ' + minutes + 'm';
    }

    return minutes + 'm';
}

function bindPubGolfChat(root) {
    if (!(root instanceof HTMLElement)) {
        return;
    }

    const stateNode = root.querySelector('[data-chat-state]');
    const panel = root.querySelector('[data-chat-panel]');
    const toggle = root.querySelector('[data-chat-toggle]');
    const close = root.querySelector('[data-chat-close]');
    const backdrop = root.querySelector('[data-chat-backdrop]');
    const messages = root.querySelector('[data-chat-messages]');
    const unreadBadge = root.querySelector('[data-chat-unread]');
    const mentionBadge = root.querySelector('[data-chat-mentions]');
    const form = root.querySelector('[data-chat-form]');
    const input = root.querySelector('[data-chat-input]');
    const photoInput = root.querySelector('[data-chat-photo]');
    const photoName = root.querySelector('[data-chat-photo-name]');
    const errorNode = root.querySelector('[data-chat-error]');
    const mentionMenu = root.querySelector('[data-chat-mentions-menu]');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    let state = JSON.parse(stateNode?.textContent || '{"can_send":false,"unread":0,"mentions":0,"latest_id":0,"mentionable":[],"messages":[]}');
    let open = false;
    let mentionIndex = -1;

    const headers = () => ({
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrf,
        'X-Requested-With': 'XMLHttpRequest',
    });

    const applyState = (next, scrollToEnd = false) => {
        state = next;
        renderMessages(scrollToEnd);
        renderBadges();
    };

    const renderBadges = () => {
        const unread = Number(state.unread) || 0;
        const mentions = Number(state.mentions) || 0;

        if (unreadBadge instanceof HTMLElement) {
            unreadBadge.textContent = String(unread);
            unreadBadge.classList.toggle('hidden', unread < 1 || open);
        }

        if (mentionBadge instanceof HTMLElement) {
            mentionBadge.textContent = '@' + mentions;
            mentionBadge.classList.toggle('hidden', mentions < 1 || open);
        }
    };

    const renderMessages = (scrollToEnd) => {
        if (!(messages instanceof HTMLElement)) {
            return;
        }

        if (!Array.isArray(state.messages) || state.messages.length === 0) {
            messages.innerHTML = '<p class="px-1 py-6 text-center text-sm text-base-content/60" data-chat-empty>No messages yet. Say hi.</p>';
            return;
        }

        messages.replaceChildren(...state.messages.map((message) => messageNode(message, state.mentionable || [])));

        if (scrollToEnd) {
            messages.scrollTop = messages.scrollHeight;
        }
    };

    const isMobileChat = () => window.matchMedia('(max-width: 639px)').matches;

    const dockToVisibleViewport = () => {
        if (!isMobileChat() || !open) {
            root.style.top = '';
            root.style.height = '';
            document.body.classList.remove('overflow-hidden');

            return;
        }

        const viewport = window.visualViewport;
        root.style.top = viewport ? `${Math.round(viewport.offsetTop)}px` : '0';
        root.style.height = viewport ? `${Math.round(viewport.height)}px` : '100dvh';
        document.body.classList.add('overflow-hidden');
    };

    const setOpen = (next) => {
        open = next;
        root.toggleAttribute('data-open', open);
        panel?.classList.toggle('hidden', !open);
        panel?.classList.toggle('flex', open);
        backdrop?.classList.toggle('hidden', !open || !isMobileChat());
        toggle?.setAttribute('aria-expanded', open ? 'true' : 'false');
        renderBadges();
        dockToVisibleViewport();

        if (open) {
            renderMessages(true);
            markRead();
            if (input instanceof HTMLTextAreaElement) {
                input.focus();
            }
        }
    };

    const poll = async () => {
        try {
            const response = await fetch(root.getAttribute('data-poll-url') || '', {
                headers: headers(),
                credentials: 'same-origin',
            });

            if (!response.ok) {
                return;
            }

            applyState(await response.json(), open);
            if (open) {
                markRead();
            }
        } catch {
            return;
        }
    };

    const markRead = async () => {
        if (!state.latest_id) {
            return;
        }

        try {
            const response = await fetch(root.getAttribute('data-read-url') || '', {
                method: 'POST',
                headers: {
                    ...headers(),
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ last_read_message_id: state.latest_id }),
            });

            if (!response.ok) {
                return;
            }

            applyState(await response.json(), open);
        } catch {
            return;
        }
    };

    const showError = (message) => {
        if (!(errorNode instanceof HTMLElement)) {
            return;
        }

        errorNode.textContent = message;
        errorNode.classList.toggle('hidden', message === '');
    };

    toggle?.addEventListener('click', () => setOpen(!open));
    close?.addEventListener('click', () => setOpen(false));
    backdrop?.addEventListener('click', () => setOpen(false));

    document.addEventListener('pointerdown', (event) => {
        if (!open || !(event.target instanceof Node) || root.contains(event.target)) {
            return;
        }

        setOpen(false);
    });

    document.addEventListener('keydown', (event) => {
        if (open && event.key === 'Escape') {
            setOpen(false);
        }
    });

    window.visualViewport?.addEventListener('resize', dockToVisibleViewport);
    window.visualViewport?.addEventListener('scroll', dockToVisibleViewport);
    window.addEventListener('resize', dockToVisibleViewport);

    photoInput?.addEventListener('change', () => {
        const file = photoInput instanceof HTMLInputElement ? photoInput.files?.[0] : null;

        if (photoName instanceof HTMLElement) {
            photoName.textContent = file ? file.name : '';
            photoName.classList.toggle('hidden', !file);
        }
    });

    input?.addEventListener('input', () => {
        updateMentionMenu(input, mentionMenu, state.mentionable || [], (index) => {
            mentionIndex = index;
        });
        autoSize(input);
    });

    input?.addEventListener('keydown', (event) => {
        if (!(mentionMenu instanceof HTMLElement) || mentionMenu.classList.contains('hidden')) {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                form?.requestSubmit();
            }
            return;
        }

        const options = [...mentionMenu.querySelectorAll('[data-mention-option]')];

        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            mentionIndex = cycleMention(options, mentionIndex, event.key === 'ArrowDown' ? 1 : -1);
            return;
        }

        if (event.key === 'Enter' || event.key === 'Tab') {
            const option = options[Math.max(0, mentionIndex)];
            if (option instanceof HTMLElement) {
                event.preventDefault();
                insertMention(input, option.getAttribute('data-mention-handle') || '');
                mentionMenu.classList.add('hidden');
            }
        }

        if (event.key === 'Escape') {
            mentionMenu.classList.add('hidden');
        }
    });

    mentionMenu?.addEventListener('mousedown', (event) => {
        const option = event.target instanceof HTMLElement ? event.target.closest('[data-mention-option]') : null;

        if (!(option instanceof HTMLElement)) {
            return;
        }

        event.preventDefault();
        insertMention(input, option.getAttribute('data-mention-handle') || '');
        mentionMenu.classList.add('hidden');
    });

    form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        showError('');

        const body = input instanceof HTMLTextAreaElement ? input.value.trim() : '';
        let file = photoInput instanceof HTMLInputElement ? photoInput.files?.[0] : null;

        if (!body && !file) {
            showError('Write a message or add a photo.');
            return;
        }

        if (file && isHeicPhoto(file)) {
            if (photoInput instanceof HTMLInputElement) {
                photoInput.value = '';
            }
            showError('Use a JPEG, PNG, or WebP photo. HEIC is not allowed.');
            return;
        }

        if (file) {
            try {
                file = await compressPubGolfPhoto(file);
            } catch {
                showError('That photo could not be read. Try a JPEG, PNG, or WebP.');
                return;
            }
        }

        const payload = new FormData();
        if (body) {
            payload.append('body', body);
        }
        if (file) {
            payload.append('photo', file);
        }

        const button = form.querySelector('button[type="submit"]');
        setButtonLoading(button, true);

        try {
            const response = await fetch(root.getAttribute('data-store-url') || '', {
                method: 'POST',
                headers: headers(),
                credentials: 'same-origin',
                body: payload,
            });
            const result = await response.json().catch(() => null);

            if (!response.ok) {
                showError(result?.message || result?.errors?.body?.[0] || result?.errors?.photo?.[0] || 'Message could not be sent.');
                return;
            }

            if (input instanceof HTMLTextAreaElement) {
                input.value = '';
                autoSize(input);
            }
            if (photoInput instanceof HTMLInputElement) {
                photoInput.value = '';
            }
            if (photoName instanceof HTMLElement) {
                photoName.textContent = '';
                photoName.classList.add('hidden');
            }

            applyState(result, true);
        } catch {
            showError('Message could not be sent.');
        } finally {
            setButtonLoading(button, false);
        }
    });

    renderBadges();
    window.setInterval(poll, 4000);
}

function messageNode(message, mentionable) {
    const article = document.createElement('article');
    article.className = 'chat ' + (message.is_you ? 'chat-end' : 'chat-start');

    const header = document.createElement('div');
    header.className = 'chat-header text-xs';
    header.append(document.createTextNode(message.name + ' '));
    const time = document.createElement('time');
    time.className = 'opacity-50';
    time.textContent = message.created_at || '';
    header.append(time);

    const bubble = document.createElement('div');
    bubble.className = 'chat-bubble whitespace-pre-wrap break-words' + (message.mentioned_you ? ' chat-bubble-secondary' : '');

    if (message.photo_url) {
        const image = document.createElement('img');
        image.src = message.photo_url;
        image.alt = '';
        image.className = 'mb-2 max-h-48 w-full rounded-xl object-cover';
        bubble.append(image);
    }

    if (message.body) {
        const text = document.createElement('span');
        text.innerHTML = highlightMentions(message.body, mentionable);
        bubble.append(text);
    }

    article.append(header, bubble);

    return article;
}

function highlightMentions(body, mentionable) {
    const handles = new Set((mentionable || []).map((person) => person.handle));
    const escaped = escapeHtml(body);

    return escaped.replace(/@([A-Za-z0-9]+)/g, (match, handle) => {
        if (!handles.has(handle.toLowerCase())) {
            return match;
        }

        return `<span class="font-semibold">@${handle}</span>`;
    });
}

function escapeHtml(value) {
    return value
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

function updateMentionMenu(input, menu, mentionable, onIndex) {
    if (!(input instanceof HTMLTextAreaElement) || !(menu instanceof HTMLElement)) {
        return;
    }

    const match = input.value.slice(0, input.selectionStart || 0).match(/@([A-Za-z0-9]*)$/);

    if (!match) {
        menu.classList.add('hidden');
        menu.replaceChildren();
        onIndex(-1);
        return;
    }

    const query = match[1].toLowerCase();
    const matches = mentionable.filter((person) => person.handle.startsWith(query) || person.name.toLowerCase().includes(query));

    if (matches.length === 0) {
        menu.classList.add('hidden');
        menu.replaceChildren();
        onIndex(-1);
        return;
    }

    menu.replaceChildren(...matches.map((person) => {
        const item = document.createElement('li');
        const button = document.createElement('button');
        button.type = 'button';
        button.setAttribute('data-mention-option', '');
        button.setAttribute('data-mention-handle', person.handle);
        button.textContent = '@' + person.handle + ' · ' + person.name;
        item.append(button);
        return item;
    }));
    menu.classList.remove('hidden');
    onIndex(0);
    highlightMentionOption(menu, 0);
}

function cycleMention(options, current, delta) {
    if (options.length === 0) {
        return -1;
    }

    const next = (Math.max(current, 0) + delta + options.length) % options.length;
    highlightMentionOption(options[0].closest('[data-chat-mentions-menu]'), next);

    return next;
}

function highlightMentionOption(menu, index) {
    if (!(menu instanceof HTMLElement)) {
        return;
    }

    menu.querySelectorAll('[data-mention-option]').forEach((option, optionIndex) => {
        option.classList.toggle('active', optionIndex === index);
    });
}

function insertMention(input, handle) {
    if (!(input instanceof HTMLTextAreaElement) || handle === '') {
        return;
    }

    const cursor = input.selectionStart || 0;
    const before = input.value.slice(0, cursor).replace(/@([A-Za-z0-9]*)$/, '@' + handle + ' ');
    input.value = before + input.value.slice(cursor);
    input.focus();
    input.selectionStart = input.selectionEnd = before.length;
}

function autoSize(input) {
    if (!(input instanceof HTMLTextAreaElement)) {
        return;
    }

    input.style.height = 'auto';
    input.style.height = Math.min(input.scrollHeight, 120) + 'px';
}

function renderRecap(root) {
    if (!(root instanceof HTMLElement)) {
        return;
    }

    const node = root.querySelector('[data-pub-golf-recap-charts]');
    const charts = JSON.parse(node?.textContent || '{"hourly":[],"categories":[],"cumulative":[]}');
    const content = cssVarColor('--color-base-content');
    const theme = {
        text: content,
        muted: withAlpha(content, 0.72),
        grid: withAlpha(content, 0.14),
        primary: '#D82820',
        secondary: '#FEC523',
        accent: '#2A9D8F',
    };
    const palette = ['#D82820', '#FEC523', '#2A9D8F', '#E85D04', '#283030', '#9B2226', '#F4A261'];

    Chart.defaults.color = theme.text;
    Chart.defaults.borderColor = theme.grid;
    Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
    Chart.defaults.font.size = 12;

    const hourlyCanvas = root.querySelector('[data-chart="hourly"]');
    const categoryCanvas = root.querySelector('[data-chart="categories"]');
    const cumulativeCanvas = root.querySelector('[data-chart="cumulative"]');

    if (hourlyCanvas instanceof HTMLCanvasElement) {
        new Chart(hourlyCanvas, {
            type: 'bar',
            data: {
                labels: charts.hourly.map((row) => row.label),
                datasets: [{
                    label: 'Drinks',
                    data: charts.hourly.map((row) => row.count),
                    backgroundColor: theme.primary,
                    borderRadius: 8,
                }],
            },
            options: scaleOptions(theme, 'Drinks'),
        });
    }

    if (categoryCanvas instanceof HTMLCanvasElement) {
        new Chart(categoryCanvas, {
            type: 'doughnut',
            data: {
                labels: charts.categories.map((row) => row.label),
                datasets: [{
                    data: charts.categories.map((row) => row.count),
                    backgroundColor: palette,
                    borderWidth: 0,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: theme.text },
                    },
                },
            },
        });
    }

    if (cumulativeCanvas instanceof HTMLCanvasElement) {
        new Chart(cumulativeCanvas, {
            type: 'line',
            data: {
                labels: charts.cumulative.map((row) => row.label),
                datasets: [{
                    label: 'Running total',
                    data: charts.cumulative.map((row) => row.count),
                    borderColor: theme.primary,
                    backgroundColor: withAlpha(theme.primary, 0.16),
                    fill: true,
                    tension: 0.3,
                    pointBackgroundColor: theme.secondary,
                }],
            },
            options: scaleOptions(theme, 'Drinks'),
        });
    }
}

function scaleOptions(theme, yTitle) {
    return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
        },
        scales: {
            x: {
                grid: { color: theme.grid },
                ticks: { color: theme.muted, maxRotation: 0 },
            },
            y: {
                beginAtZero: true,
                ticks: { color: theme.muted, precision: 0 },
                grid: { color: theme.grid },
                title: { display: true, text: yTitle, color: theme.muted },
            },
        },
    };
}

function cssVarColor(name) {
    const value = getComputedStyle(document.body).getPropertyValue(name).trim();

    return value || '#1c1c1c';
}

function withAlpha(color, alpha) {
    if (color.startsWith('#')) {
        const hex = color.slice(1);
        const full = hex.length === 3
            ? hex.split('').map((part) => part + part).join('')
            : hex;
        const int = Number.parseInt(full, 16);
        const r = (int >> 16) & 255;
        const g = (int >> 8) & 255;
        const b = int & 255;

        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    }

    return color;
}
