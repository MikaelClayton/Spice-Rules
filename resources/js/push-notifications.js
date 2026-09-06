import { setButtonLoading } from './button-loading';

const root = document.querySelector('[data-push-notifications]');

if (root) {
    setupPushNotifications(root);
}

function setupPushNotifications(rootEl) {
    const button = rootEl.querySelector('[data-enable-push]');
    const status = rootEl.querySelector('[data-push-status]');
    const vapidKey = rootEl.getAttribute('data-vapid-key') ?? '';
    const tokenUrl = rootEl.getAttribute('data-token-url') ?? '';
    let config = {};

    try {
        config = JSON.parse(rootEl.getAttribute('data-firebase-config') ?? '{}');
    } catch (error) {
        setStatus(status, 'Could not read the notification settings. Refresh and try again.');
        hideButton(button);

        return;
    }

    if (!button) {
        return;
    }

    if (!pushIsSupported()) {
        setStatus(status, iPhoneHint());
        hideButton(button);

        return;
    }

    button.addEventListener('click', async () => {
        setButtonLoading(button, true);
        setStatus(status, 'Asking for permission…');

        try {
            await enablePush(config, vapidKey, tokenUrl, status, button);
        } catch (error) {
            setStatus(status, errorMessage(error));
        } finally {
            setButtonLoading(button, false);
        }
    });

    if (window.Notification?.permission === 'granted') {
        enablePush(config, vapidKey, tokenUrl, status, button).catch((error) => {
            setStatus(status, errorMessage(error));
        });
    } else if (window.Notification?.permission === 'denied') {
        setStatus(status, 'Notifications are blocked for this site. Allow them in the browser settings.');
        hideButton(button);
    }
}

function pushIsSupported() {
    if (!window.firebase || typeof window.firebase.initializeApp !== 'function') {
        return false;
    }

    if (!('Notification' in window) || !('serviceWorker' in navigator) || !('PushManager' in window)) {
        return false;
    }

    const messaging = window.firebase.messaging;

    if (typeof messaging?.isSupported !== 'function') {
        return true;
    }

    try {
        return Boolean(messaging.isSupported());
    } catch (error) {
        return false;
    }
}

function iPhoneHint() {
    const isIos = /iPad|iPhone|iPod/.test(navigator.userAgent)
        || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    const standalone = window.navigator.standalone === true
        || window.matchMedia('(display-mode: standalone)').matches;

    if (isIos && !standalone) {
        return 'On iPhone, tap Share, Add to Home Screen, open Spice Rules from there, then enable notifications.';
    }

    return 'Push notifications are not available in this browser.';
}

async function enablePush(config, vapidKey, tokenUrl, statusEl, button) {
    if (!window.firebase.apps.length) {
        window.firebase.initializeApp(config);
    }

    const permission = await Notification.requestPermission();

    if (permission !== 'granted') {
        setStatus(statusEl, 'Notifications were not enabled.');

        return;
    }

    setStatus(statusEl, 'Saving this device…');

    const registration = await navigator.serviceWorker.register('/firebase-messaging-sw.js', { scope: '/' });
    await navigator.serviceWorker.ready;
    const messaging = window.firebase.messaging();
    const token = await messaging.getToken({
        vapidKey,
        serviceWorkerRegistration: registration,
    });

    if (!token) {
        setStatus(statusEl, 'Could not create a push token. Try again.');

        return;
    }

    const response = await fetch(tokenUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify({ token }),
    });

    if (!response.ok) {
        setStatus(statusEl, 'Could not save this device. Try again.');

        return;
    }

    if (button) {
        button.textContent = 'Notifications are on';
    }

    setStatus(statusEl, 'This device will get a ping when someone finishes today\'s daily.');
}

function errorMessage(error) {
    const code = error?.code ?? '';

    if (String(code).includes('permission-blocked') || String(code).includes('permission-default')) {
        return 'Notifications were not enabled.';
    }

    if (String(code).includes('unsupported-browser') || String(code).includes('indexed-db-unsupported')) {
        return iPhoneHint();
    }

    const message = typeof error?.message === 'string' && error.message !== ''
        ? error.message
        : 'Could not enable notifications. Try again.';

    return message.length > 180 ? 'Could not enable notifications. Try again.' : message;
}

function hideButton(button) {
    if (button) {
        button.hidden = true;
    }
}

function setStatus(el, text) {
    if (!el) {
        return;
    }

    el.textContent = text;
    el.classList.remove('hidden');
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}
