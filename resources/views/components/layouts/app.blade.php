<!doctype html>
<html lang="de" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#17140f">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>WolfCash</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-ink font-sans text-fg antialiased">

@php
    $isFloor = request()->routeIs(['pos.*', 'production.index']);

    $adminLinks = [
        ['route' => 'admin.dashboard', 'label' => 'Übersicht'],
        ['route' => 'admin.devices', 'label' => 'Geräte'],
        ['route' => 'admin.tables', 'label' => 'Tische'],
        ['route' => 'admin.products', 'label' => 'Produkte'],
        ['route' => 'admin.product-groups', 'label' => 'Produktgruppen'],
        ['route' => 'admin.product-categories', 'label' => 'Kategorien'],
        ['route' => 'admin.printers', 'label' => 'Drucker'],
        ['route' => 'admin.production-stations', 'label' => 'Arbeitsplätze'],
        ['route' => 'admin.print-jobs', 'label' => 'Druckjobs'],
    ];
@endphp

@if($isFloor)

    {{-- Schlanke Kopfzeile: maximaler Platz fürs Arbeiten am Tisch/Tresen --}}
    <header class="sticky top-0 z-40 flex items-center justify-between border-b border-line/70 bg-ink/95 px-4 py-2.5 backdrop-blur">
        <a href="{{ route('pos.index') }}" class="flex items-center gap-2 font-display text-base font-semibold tracking-tight text-fg">
            <span class="inline-block h-2 w-2 rounded-full bg-accent"></span>
            WolfCash
        </a>

        <a
            href="{{ route('dashboard') }}"
            class="rounded-full border border-line px-3 py-1.5 text-xs font-medium text-dim transition hover:border-accent hover:text-accent"
        >
            Verlassen
        </a>
    </header>

    <main>
        {{ $slot }}
    </main>

@else

    {{-- Backoffice-Navigation --}}
    <header x-data="{ open: false }" class="sticky top-0 z-40 border-b border-line/70 bg-surface/90 backdrop-blur">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 sm:px-6">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2 font-display text-lg font-semibold tracking-tight text-fg">
                <span class="inline-block h-2.5 w-2.5 rounded-full bg-accent"></span>
                WolfCash
            </a>

            <nav class=" items-center gap-1 lg:flex">
                @foreach($adminLinks as $link)
                    <a
                        href="{{ route($link['route']) }}"
                        class="rounded-full px-3 py-1.5 text-sm font-medium transition
                            {{ request()->routeIs($link['route']) ? 'bg-accent text-accent-ink' : 'text-dim hover:bg-surface-2 hover:text-fg' }}"
                    >
                        {{ $link['label'] }}
                    </a>
                @endforeach

                <a href="{{ route('pos.index') }}" class="ml-2 rounded-full bg-accent px-4 py-1.5 text-sm font-semibold text-accent-ink transition hover:bg-accent-strong" >
                    Kasse öffnen
                </a>
            </nav>

            <button @click="open = !open" class="rounded-lg p-2 text-dim hover:text-fg lg:hidden" aria-label="Menü öffnen" >
                <svg x-show="!open" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
                <svg x-show="open" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <nav x-show="open" x-transition x-cloak class="flex flex-col gap-1 border-t border-line/70 px-4 py-3 lg:hidden" >
            @foreach($adminLinks as $link)
                <a
                    href="{{ route($link['route']) }}"
                    class="rounded-lg px-3 py-2 text-sm font-medium
                        {{ request()->routeIs($link['route']) ? 'bg-accent text-accent-ink' : 'text-dim hover:bg-surface-2 hover:text-fg' }}"
                >
                    {{ $link['label'] }}
                </a>
            @endforeach

            <a href="{{ route('pos.index') }}" class="mt-1 rounded-lg bg-accent px-3 py-2 text-center text-sm font-semibold text-accent-ink">
                Kasse öffnen
            </a>
        </nav>
    </header>

    <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6">
        {{ $slot }}
    </main>

@endif

@livewireScripts

@if($isFloor)
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

            if (data.status === 'pending') {
                document.body.innerHTML = `
                <div style="min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.5rem;padding:2rem;text-align:center;background:#17140f;color:#f5f1e8;font-family:Inter,sans-serif;">
                    <div style="font-size:2.5rem;">⏳</div>
                    <h1 style="font-size:1.25rem;font-weight:600;">Gerät wartet auf Freigabe</h1>
                    <p style="color:#a89f8d;">Bitte Admin kontaktieren</p>
                </div>
            `;
            }

            if (data.status === 'blocked') {
                document.body.innerHTML = `
                <div style="min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.5rem;padding:2rem;text-align:center;background:#17140f;color:#e2555f;font-family:Inter,sans-serif;">
                    <div style="font-size:2.5rem;">⛔</div>
                    <h1 style="font-size:1.25rem;font-weight:600;">Zugriff gesperrt</h1>
                </div>
            `;
            }
        }

        checkDevice();
    </script>
@endif
</body>
</html>
