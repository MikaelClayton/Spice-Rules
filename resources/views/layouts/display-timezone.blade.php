<script>
    (() => {
        const key = 'display_timezone';
        let timezone = '';

        try {
            timezone = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
        } catch {
            return;
        }

        if (timezone === '') {
            return;
        }

        const match = document.cookie.split('; ').find((row) => row.startsWith(key + '='));
        const current = match ? decodeURIComponent(match.slice(key.length + 1)) : '';

        if (current === timezone) {
            return;
        }

        if (sessionStorage.getItem(key) === timezone) {
            return;
        }

        sessionStorage.setItem(key, timezone);
        document.cookie = key + '=' + encodeURIComponent(timezone)
            + ';path=/;max-age=31536000;samesite=lax'
            + (window.location.protocol === 'https:' ? ';secure' : '');
        window.location.reload();
    })();
</script>
