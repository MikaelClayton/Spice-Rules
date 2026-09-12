import './button-loading';
import './live-poll';
import './geoguessr-board';
import './geoguessr-challenges';
import './geoguessr-profile-challenges';
import './pub-golf';
import './push-notifications';
import './wickets-sip-stepper';
import './wickets-people-picker';

document.querySelectorAll('[data-tabs] [data-tab]').forEach((tab) => {
    tab.addEventListener('change', () => {
        const url = new URL(window.location.href);
        const name = tab.getAttribute('data-tab');
        const defaultTab = tab.closest('[data-tabs]')?.getAttribute('data-default-tab') || 'account';

        if (name === defaultTab) {
            url.searchParams.delete('tab');
        } else {
            url.searchParams.set('tab', name);
        }

        window.history.replaceState({}, '', url);
    });
});

