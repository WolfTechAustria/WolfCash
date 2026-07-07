<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Kassensystem</title>

    @livewireStyles
</head>
<body>

<nav>
    <a href="/admin">Dashboard</a>
    <a href="/admin/devices">Devices</a>
    <a href="/admin/tables">Tische</a>
    <a href="/admin/products">Produkte</a>
    <a href="/admin/product-groups">Produktgruppen</a>
    <a href="/admin/product-categories">Produktkategorien</a>
    <a href="/admin/printers">Drucker</a>
    <a href="/admin/production-stations">Produktionsstation</a>
    <a href="/admin/print-jobs">Druckjobs</a>

</nav>

<main>
    {{ $slot }}
</main>

@livewireScripts
</body>
</html>

<script>
    function getFingerprint() {
        let key = localStorage.getItem('device_fingerprint');

        if (!key) {
            key = crypto.randomUUID();
            localStorage.setItem('device_fingerprint', key);
        }

        return key;
    }

    async function checkDevice() {
        const fingerprint = getFingerprint();

        // 1. Register Device (falls neu)
        await fetch('/api/device/register', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                fingerprint: fingerprint,
                platform: navigator.userAgent
            })
        });

        // 2. Status check
        const res = await fetch('/api/device/status', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                fingerprint: fingerprint
            })
        });

        const data = await res.json();

        // 3. Gate Logik
        if (data.status === 'pending') {
            document.body.innerHTML = `
            <div style="padding:40px;text-align:center;">
                <h1>⏳ Gerät wartet auf Freigabe</h1>
                <p>Bitte Admin kontaktieren</p>
            </div>
        `;
        }

        if (data.status === 'blocked') {
            document.body.innerHTML = `
            <div style="padding:40px;text-align:center;color:red;">
                <h1>⛔ Zugriff gesperrt</h1>
            </div>
        `;
        }
    }

    checkDevice();
</script>
