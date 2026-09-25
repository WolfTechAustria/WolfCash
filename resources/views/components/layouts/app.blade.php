<!doctype html>
<html lang="de" class="{{ request()->cookie('wolfcash_theme', 'dark') === 'light' ? '' : 'dark' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#17140f">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">

    <title>WolfCash</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-ink font-sans text-fg antialiased">

@php
    $isFloor = request()->routeIs(['pos.*', 'production.index']);

    /*
     * Sidebar-Navigation für den Backoffice-Bereich, gruppiert
     * nach Themenblock. "Einstellungen" ist bewusst nicht Teil
     * dieser Liste — sie sitzt fix unten links im Sidebar-Fuß.
     */
    $navGroups = [
        [
            'items' => [
                ['route' => 'admin.dashboard', 'label' => 'Übersicht', 'icon' => 'home'],
                ['route' => 'admin.tables', 'label' => 'Tische', 'icon' => 'building-storefront'],
                ['route' => 'admin.products', 'label' => 'Produkte', 'icon' => 'shopping-bag'],
                ['route' => 'admin.product-groups', 'label' => 'Produktgruppen', 'icon' => 'queue-list'],
                ['route' => 'admin.product-categories', 'label' => 'Kategorien', 'icon' => 'list-bullet'],
            ],
        ],
        [
            'title' => 'Bestellungen',
            'items' => [
                ['route' => 'admin.orders', 'label' => 'Bestellungen', 'icon' => 'clipboard-document-list'],
                ['route' => 'admin.cancellations', 'label' => 'Stornos', 'icon' => 'receipt-refund'],
                ['route' => 'admin.daily-summary', 'label' => 'Tagesübersicht', 'icon' => 'chart-pie'],
                ['route' => 'admin.daily-closings', 'label' => 'Tagesabschlüsse', 'icon' => 'clipboard-document-check'],
                ['route' => 'admin.product-reports', 'label' => 'Verkaufsstatistik', 'icon' => 'chart-bar'],
            ],
        ],
        [
            'title' => 'Technik',
            'items' => [
                ['route' => 'admin.printers', 'label' => 'Drucker', 'icon' => 'printer'],
                ['route' => 'admin.production-stations', 'label' => 'Arbeitsplätze', 'icon' => 'building-office'],
                ['route' => 'admin.print-jobs', 'label' => 'Druckjobs', 'icon' => 'inbox-stack'],
                ['route' => 'admin.devices', 'label' => 'Geräte', 'icon' => 'device-tablet'],
            ],
        ],
        [
            'title' => 'Gefahrenzone',
            'items' => [
                ['route' => 'admin.system-reset', 'label' => 'System zurücksetzen', 'icon' => 'exclamation-triangle', 'danger' => true],
            ],
        ],
    ];
@endphp

@if($isFloor)

    {{-- Schlanke Kopfzeile: maximaler Platz fürs Arbeiten am Tisch/Tresen --}}
    <header class="sticky top-0 z-40 flex items-center justify-between border-b border-line/70 bg-ink/95 px-4 py-2.5 backdrop-blur">
        <a href="{{ route('pos.index') }}" class="flex items-center gap-2 font-display text-base font-semibold tracking-tight text-fg">
            <span class="inline-block h-2 w-2 rounded-full bg-accent"></span>
            WolfCash
        </a>

        <div class="flex items-center gap-2">
            <button
                type="button"
                id="theme-toggle"
                aria-label="Tag/Nacht-Ansicht umschalten"
                class="flex h-8 w-8 items-center justify-center rounded-full border border-line text-dim transition hover:border-accent hover:text-accent"
            >
                <span id="theme-toggle-icon">🌙</span>
            </button>

            <a
                href="{{ route('dashboard') }}"
                class="rounded-full border border-line px-3 py-1.5 text-xs font-medium text-dim transition hover:border-accent hover:text-accent"
            >
                Verlassen
            </a>
        </div>
    </header>

    <script>
        (function () {
            const button = document.getElementById('theme-toggle');
            const icon = document.getElementById('theme-toggle-icon');

            function isDark() {
                return document.documentElement.classList.contains('dark');
            }

            function syncIcon() {
                icon.textContent = isDark() ? '🌙' : '☀️';
            }

            syncIcon();

            button.addEventListener('click', function () {
                document.documentElement.classList.toggle('dark');

                const theme = isDark() ? 'dark' : 'light';

                document.cookie = 'wolfcash_theme=' + theme + '; max-age=' + (60 * 60 * 24 * 365) + '; path=/';

                syncIcon();
            });
        })();
    </script>

    <main>
        {{ $slot }}
    </main>

@else

    {{-- Backoffice: Sidebar links, Einstellungen unten links (Starterkit-Layout) --}}
    <div x-data="{ sidebarOpen: false }" class="lg:flex lg:min-h-screen">

        {{-- Mobile Topbar --}}
        <div class="sticky top-0 z-40 flex items-center justify-between border-b border-line/70 bg-surface/90 px-4 py-3 backdrop-blur lg:hidden">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2 font-display text-lg font-semibold tracking-tight text-fg">
                <span class="inline-block h-2.5 w-2.5 rounded-full bg-accent"></span>
                WolfCash
            </a>

            <button @click="sidebarOpen = true" class="rounded-lg p-2 text-dim hover:text-fg" aria-label="Menü öffnen">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
        </div>

        {{-- Mobiler Hintergrund --}}
        <div
            x-show="sidebarOpen"
            x-cloak
            x-transition:enter="transition-opacity ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-40 bg-black/60 lg:hidden"
            @click="sidebarOpen = false"
        ></div>

        {{-- Sidebar --}}
        {{--
            Bewusst per Klassen-Transform statt x-show gesteuert: Alpines
            x-show setzt ein inline "display:none", das jede responsive
            Tailwind-Klasse (z. B. lg:flex) übersticht und die Sidebar auf
            großen Bildschirmen unsichtbar machen würde, bis einmal manuell
            getoggelt wurde. Reine Klassen lassen sich per lg:-Variante
            zuverlässig überschreiben.
        --}}
        <aside
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
            class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col border-r border-line/70 bg-surface transition-transform duration-200 lg:sticky lg:top-0 lg:z-0 lg:h-screen lg:translate-x-0 lg:transition-none"
        >
            <div class="flex shrink-0 items-center justify-between px-5 py-4">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-2 font-display text-lg font-semibold tracking-tight text-fg">
                    <span class="inline-block h-2.5 w-2.5 rounded-full bg-accent"></span>
                    WolfCash
                </a>

                <button @click="sidebarOpen = false" class="text-dim lg:hidden" aria-label="Menü schließen">
                    <flux:icon.x-mark class="size-5" />
                </button>
            </div>

            <nav class="min-h-0 flex-1 overflow-y-auto px-3 pb-4">
                @foreach($navGroups as $group)

                    @if(! $loop->first)
                        <flux:separator class="my-3" />
                    @endif

                    @if(isset($group['title']))
                        <p class="mb-1 mt-2 px-3 text-xs font-semibold uppercase tracking-wide text-dim">
                            {{ $group['title'] }}
                        </p>
                    @endif

                    @foreach($group['items'] as $link)
                        <a
                            href="{{ route($link['route']) }}"
                            class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium transition
                                {{ ($link['danger'] ?? false)
                                    ? (request()->routeIs($link['route']) ? 'bg-occupied/15 text-occupied' : 'text-occupied/80 hover:bg-occupied/10 hover:text-occupied')
                                    : (request()->routeIs($link['route']) ? 'bg-accent text-accent-ink' : 'text-dim hover:bg-surface-2 hover:text-fg') }}"
                        >
                            <flux:icon :icon="$link['icon']" class="size-4.5 shrink-0" />
                            {{ $link['label'] }}
                        </a>
                    @endforeach

                @endforeach
            </nav>

            {{-- Fuß: Einstellungen unten links --}}
            <div class="shrink-0 border-t border-line/70 p-3">
                <flux:dropdown position="top" align="start" class="w-full">
                    <button
                        type="button"
                        class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium transition
                            {{ request()->routeIs('admin.settings') ? 'bg-accent text-accent-ink' : 'text-dim hover:bg-surface-2 hover:text-fg' }}"
                    >
                        <flux:icon.cog-6-tooth class="size-4.5 shrink-0" />
                        Einstellungen
                    </button>

                    <flux:menu>
                        <flux:menu.item href="{{ route('admin.settings') }}" icon="cog-6-tooth">
                            Allgemeine Einstellungen
                        </flux:menu.item>
                        <flux:menu.separator />
                        <flux:menu.item href="{{ route('pos.index') }}" icon="shopping-cart">
                            Kasse öffnen
                        </flux:menu.item>
                    </flux:menu>
                </flux:dropdown>
            </div>
        </aside>

        <main class="min-w-0 flex-1 px-4 py-6 sm:px-6 lg:px-8">
            {{ $slot }}
        </main>

    </div>

@endif

@livewireScripts
@fluxScripts

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
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    uuid: fingerprint,
                    fingerprint: fingerprint,
                    name: 'Browser ' + (navigator.userAgentData?.platform || navigator.platform || 'Web'),
                    platform: 'web'
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

            /*
            //DeviceToken Check

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

             */
        }

        checkDevice();
    </script>
@endif
</body>
</html>
