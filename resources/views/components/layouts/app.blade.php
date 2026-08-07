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
        ['route' => 'admin.orders', 'label' => 'Bestellungen'],
        ['route' => 'admin.cancellations', 'label' => 'Stornos'],
        ['route' => 'admin.daily-summary', 'label' => 'Tagesübersicht'],
        ['route' => 'admin.devices', 'label' => 'Geräte'],
        ['route' => 'admin.tables', 'label' => 'Tische'],
        ['route' => 'admin.products', 'label' => 'Produkte'],
        ['route' => 'admin.product-groups', 'label' => 'Produktgruppen'],
        ['route' => 'admin.product-categories', 'label' => 'Kategorien'],
        ['route' => 'admin.printers', 'label' => 'Drucker'],
        ['route' => 'admin.production-stations', 'label' => 'Arbeitsplätze'],
        ['route' => 'admin.print-jobs', 'label' => 'Druckjobs'],
    ];

    $primaryLinks = [
        ['route' => 'admin.dashboard', 'label' => 'Übersicht'],
        ['route' => 'admin.tables', 'label' => 'Tische'],
        ['route' => 'admin.products', 'label' => 'Produkte'],
        ['route' => 'admin.orders', 'label' => 'Bestellungen'],
        ['route' => 'admin.cancellations', 'label' => 'Stornos'],
        ['route' => 'admin.daily-summary', 'label' => 'Tagesübersicht'],
        ['route' => 'admin.daily-closings', 'label' => 'Tagesabschlüsse'],
        ['route' => 'admin.product-reports', 'label' => 'Produktauswertung'],
    ];

    $settingsLinks = [
        ['route' => 'admin.product-groups', 'label' => 'Produktgruppen'],
        ['route' => 'admin.product-categories', 'label' => 'Kategorien'],
        ['route' => 'admin.printers', 'label' => 'Drucker'],
        ['route' => 'admin.production-stations', 'label' => 'Arbeitsplätze'],
        ['route' => 'admin.print-jobs', 'label' => 'Druckjobs'],
        ['route' => 'admin.devices', 'label' => 'Geräte'],
        ['route' => 'admin.settings', 'label' => 'Allgemeine Einstellungen'],
    ];

    $settingsActive = collect($settingsLinks)->contains(fn ($link) => request()->routeIs($link['route']));
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

            <nav class="hidden items-center gap-1 lg:flex">
                @foreach($primaryLinks as $link)
                    <a
                        href="{{ route($link['route']) }}"
                        class="rounded-full px-3 py-1.5 text-sm font-medium transition
                            {{ request()->routeIs($link['route']) ? 'bg-accent text-accent-ink' : 'text-dim hover:bg-surface-2 hover:text-fg' }}"
                    >
                        {{ $link['label'] }}
                    </a>
                @endforeach

                {{-- Einstellungen-Dropdown --}}
                <div class="relative" x-data="{ settingsOpen: false }" @click.outside="settingsOpen = false" @keydown.escape.window="settingsOpen = false">
                    <button
                        type="button"
                        @click="settingsOpen = !settingsOpen"
                        class="flex items-center gap-1 rounded-full px-3 py-1.5 text-sm font-medium transition
                            {{ $settingsActive ? 'bg-accent text-accent-ink' : 'text-dim hover:bg-surface-2 hover:text-fg' }}"
                    >
                        Einstellungen
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 transition-transform" :class="settingsOpen ? '-rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <div
                        x-show="settingsOpen"
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0 -translate-y-1"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        x-transition:leave="transition ease-in duration-100"
                        x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0"
                        x-cloak
                        class="absolute right-0 top-full z-50 mt-2 w-56 rounded-xl border border-line bg-surface-2 p-1.5 shadow-xl"
                    >
                        @foreach($settingsLinks as $link)
                            <a
                                href="{{ route($link['route']) }}"
                                @click="settingsOpen = false"
                                class="block rounded-lg px-3 py-2 text-sm font-medium transition
                                    {{ request()->routeIs($link['route']) ? 'bg-accent text-accent-ink' : 'text-dim hover:bg-surface hover:text-fg' }}"
                            >
                                {{ $link['label'] }}
                            </a>
                        @endforeach
                    </div>
                </div>

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
            @foreach($primaryLinks as $link)
                <a
                    href="{{ route($link['route']) }}"
                    class="rounded-lg px-3 py-2 text-sm font-medium
                        {{ request()->routeIs($link['route']) ? 'bg-accent text-accent-ink' : 'text-dim hover:bg-surface-2 hover:text-fg' }}"
                >
                    {{ $link['label'] }}
                </a>
            @endforeach

            <p class="mb-1 mt-3 px-3 text-xs font-semibold uppercase tracking-wide text-dim">Einstellungen</p>

            @foreach($settingsLinks as $link)
                <a
                    href="{{ route($link['route']) }}"
                    class="rounded-lg px-3 py-2 text-sm font-medium
                        {{ request()->routeIs($link['route']) ? 'bg-accent text-accent-ink' : 'text-dim hover:bg-surface-2 hover:text-fg' }}"
                >
                    {{ $link['label'] }}
                </a>
            @endforeach

            <a href="{{ route('pos.index') }}" class="mt-3 rounded-lg bg-accent px-3 py-2 text-center text-sm font-semibold text-accent-ink">
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
