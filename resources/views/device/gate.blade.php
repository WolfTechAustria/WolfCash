<!doctype html>
<html lang="de" class="{{ request()->cookie('wolfcash_theme', 'dark') === 'light' ? '' : 'dark' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#17140f">
    @include('partials.upgrade-insecure-requests')

    <title>WolfCash · Gerätefreigabe</title>

    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-ink font-sans text-fg antialiased">

{{--
    Freigabe-Seite der Geräteprüfung (EnsureFloorDevice).
    Registriert diesen Browser einmalig und prüft danach laufend,
    ob ein Admin das Gerät freigegeben hat.
--}}
<main class="flex min-h-screen flex-col items-center justify-center gap-2 p-8 text-center">

    <div id="gate-blocked" @class(['hidden' => $status !== 'blocked'])>
        <div class="text-4xl">⛔</div>
        <h1 class="mt-2 font-display text-xl font-semibold text-occupied">
            Zugriff gesperrt
        </h1>
        <p class="mt-1 text-dim">
            Dieses Gerät wurde gesperrt. Bitte Admin kontaktieren.
        </p>
    </div>

    <div id="gate-pending" @class(['hidden' => $status === 'blocked'])>
        <div class="text-4xl">⏳</div>
        <h1 class="mt-2 font-display text-xl font-semibold">
            Gerät wartet auf Freigabe
        </h1>
        <p class="mt-1 text-dim">
            Bitte Admin kontaktieren und unter „Geräte“ freigeben lassen.
        </p>
        @unless($viaApp)
            <p class="mt-4 text-sm text-dim">
                Geräte-Code:
                <span id="gate-device-code" class="font-mono text-lg font-semibold text-fg">…</span>
            </p>
        @endunless
    </div>

</main>

@unless($viaApp)
<script>
    (function () {
        const STORAGE_KEY = 'device_fingerprint';
        const COOKIE = @json(\App\Http\Middleware\EnsureFloorDevice::COOKIE);

        /*
         * crypto.randomUUID() gibt es nur auf HTTPS-Seiten, im lokalen
         * Netz (http://<private IP>) daher aus getRandomValues bauen.
         */
        const randomUuid = () => {
            if (typeof crypto.randomUUID === 'function') {
                return crypto.randomUUID();
            }

            const bytes = crypto.getRandomValues(new Uint8Array(16));
            bytes[6] = (bytes[6] & 0x0f) | 0x40;
            bytes[8] = (bytes[8] & 0x3f) | 0x80;

            const hex = Array.from(bytes, b => b.toString(16).padStart(2, '0')).join('');

            return hex.slice(0, 8) + '-' + hex.slice(8, 12) + '-' + hex.slice(12, 16)
                + '-' + hex.slice(16, 20) + '-' + hex.slice(20);
        };

        let fingerprint = null;

        try {
            fingerprint = localStorage.getItem(STORAGE_KEY);

            if (!fingerprint) {
                fingerprint = randomUuid();
                localStorage.setItem(STORAGE_KEY, fingerprint);
            }
        } catch (e) {
            fingerprint = randomUuid();
        }

        /*
         * Kurzer Code im Namen, damit der Admin das Gerät
         * in der Geräteliste wiedererkennt.
         */
        const code = fingerprint.slice(0, 4).toUpperCase();
        const platform = navigator.userAgentData?.platform || navigator.platform || 'Web';
        const name = 'Browser ' + platform + ' · ' + code;

        document.getElementById('gate-device-code').textContent = code;

        document.cookie = COOKIE + '=' + fingerprint
            + '; max-age=' + (60 * 60 * 24 * 365 * 5)
            + '; path=/; SameSite=Lax'
            + (location.protocol === 'https:' ? '; Secure' : '');

        const post = (url, body) => fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(body),
        }).then(r => r.json());

        const show = (status) => {
            document.getElementById('gate-blocked').classList.toggle('hidden', status !== 'blocked');
            document.getElementById('gate-pending').classList.toggle('hidden', status === 'blocked');
        };

        async function check() {
            try {
                let data = await post('/api/device/status', { fingerprint });

                /*
                 * Nur neue Geräte registrieren, damit ein vom Admin
                 * vergebener Name nicht überschrieben wird.
                 */
                if (data.status === 'unknown') {
                    data = await post('/api/device/register', {
                        uuid: fingerprint,
                        fingerprint,
                        name,
                        platform: 'web',
                    });
                }

                /*
                 * Freigegeben: neu laden, der Server erkennt das
                 * Gerät jetzt über den Cookie.
                 */
                if (data.status === 'approved') {
                    location.reload();
                    return;
                }

                show(data.status);
            } catch (e) {
                // Netzwerkfehler: beim nächsten Durchlauf erneut versuchen.
            }

            setTimeout(check, 5000);
        }

        check();
    })();
</script>
@endunless

</body>
</html>
