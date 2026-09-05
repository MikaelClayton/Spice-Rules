import './geoguessr-board';
import './geoguessr-challenges';

document.querySelectorAll('[data-profile-tabs] [data-tab]').forEach((tab) => {
    tab.addEventListener('change', () => {
        const url = new URL(window.location.href);
        const name = tab.getAttribute('data-tab');

        if (name === 'account') {
            url.searchParams.delete('tab');
        } else {
            url.searchParams.set('tab', name);
        }

        window.history.replaceState({}, '', url);
    });
});

