@php
    $eur = fn ($value) => number_format((float) $value, 2, ',', '.').' €';

    $bytes = function (?int $value): string {
        if ($value === null) {
            return '—';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = $value > 0 ? min(count($units) - 1, (int) floor(log($value, 1024))) : 0;

        return number_format($value / (1024 ** $power), $power >= 3 ? 1 : 0, ',', '.').' '.$units[$power];
    };

    $duration = function (?int $seconds): string {
        if ($seconds === null) {
            return '—';
        }

        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return $days > 0
            ? "{$days} T {$hours} Std"
            : ($hours > 0 ? "{$hours} Std {$minutes} Min" : "{$minutes} Min");
    };

    /*
     * Schwellwerte für die Auslastungsbalken: Farbe immer zusammen
     * mit einem Text-Status, nie Farbe allein.
     */
    $level = fn (?float $percent) => match (true) {
        $percent === null => ['label' => 'n. v.', 'bar' => 'bg-line', 'text' => 'text-dim'],
        $percent >= 90 => ['label' => 'Kritisch', 'bar' => 'bg-occupied', 'text' => 'text-occupied'],
        $percent >= 70 => ['label' => 'Erhöht', 'bar' => 'bg-accent', 'text' => 'text-accent'],
        default => ['label' => 'Normal', 'bar' => 'bg-free', 'text' => 'text-free'],
    };

    $hourMax = max(1, collect($hourlyRevenue)->max('value'));
    $weekMax = max(1, collect($weeklyRevenue)->max('value'));
    $methodsTotal = max(1, collect($paymentMethods)->where('is_voucher', false)->sum('total'));
    $productMax = max(1, collect($topProducts)->max('quantity'));

    $closing = $operations['daily_closing_today'];
@endphp

<div wire:poll.30s>

    {{-- Kopf --}}
    <div class="mb-6 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
        <div>
            <h1 class="font-display text-2xl font-semibold tracking-tight">Übersicht</h1>
            <p class="mt-1 text-sm text-dim">
                {{ now()->locale('de')->translatedFormat('l, d. F Y') }}
                · aktualisiert {{ $updatedAt->format('H:i:s') }}
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('admin.daily-summary') }}" class="rounded-xl border border-line px-3 py-2 text-sm font-medium text-dim transition hover:border-accent hover:text-accent">
                Tagesübersicht
            </a>
            <a href="{{ route('pos.index') }}" class="rounded-xl bg-accent px-4 py-2 text-sm font-semibold text-accent-ink transition hover:bg-accent-strong">
                Kasse öffnen
            </a>
        </div>
    </div>

    {{-- Kennzahlen --}}
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">

        <div class="col-span-2 rounded-2xl border border-line bg-surface p-5 lg:col-span-1">
            <p class="text-xs font-semibold uppercase tracking-wide text-dim">Umsatz heute</p>
            <p class="mt-2 font-display text-3xl font-semibold tabular-nums">{{ $eur($kpis['revenue_today']) }}</p>
            <p class="mt-1 text-sm text-dim">
                @if($kpis['revenue_trend'] !== null)
                    <span class="font-medium {{ $kpis['revenue_trend'] >= 0 ? 'text-free' : 'text-occupied' }}">
                        {{ $kpis['revenue_trend'] >= 0 ? '▲' : '▼' }} {{ abs($kpis['revenue_trend']) }} %
                    </span>
                    ggü. gestern bis {{ now()->format('H:i') }}
                @else
                    Gestern bis {{ now()->format('H:i') }}: {{ $eur($kpis['revenue_yesterday_same_time']) }}
                @endif
            </p>
        </div>

        <div class="rounded-2xl border border-line bg-surface p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-dim">Zahlungen</p>
            <p class="mt-2 font-display text-3xl font-semibold tabular-nums">{{ $kpis['payments_today'] }}</p>
            <p class="mt-1 text-sm text-dim">Ø Bon {{ $eur($kpis['average_receipt']) }}</p>
        </div>

        <a href="{{ route('admin.orders') }}" class="rounded-2xl border border-line bg-surface p-5 transition hover:border-accent">
            <p class="text-xs font-semibold uppercase tracking-wide text-dim">Offen</p>
            <p class="mt-2 font-display text-3xl font-semibold tabular-nums {{ $kpis['open_amount'] > 0 ? 'text-accent' : '' }}">
                {{ $eur($kpis['open_amount']) }}
            </p>
            <p class="mt-1 text-sm text-dim">{{ $kpis['orders_open'] }} offene {{ $kpis['orders_open'] === 1 ? 'Bestellung' : 'Bestellungen' }}</p>
        </a>

        <a href="{{ route('admin.tables') }}" class="rounded-2xl border border-line bg-surface p-5 transition hover:border-accent">
            <p class="text-xs font-semibold uppercase tracking-wide text-dim">Tische belegt</p>
            <p class="mt-2 font-display text-3xl font-semibold tabular-nums">
                {{ $kpis['tables_occupied'] }}<span class="text-lg text-dim"> / {{ $kpis['tables_total'] }}</span>
            </p>
            <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-surface-2">
                <div class="h-full rounded-full bg-accent" style="width: {{ $kpis['tables_total'] > 0 ? round($kpis['tables_occupied'] / $kpis['tables_total'] * 100) : 0 }}%"></div>
            </div>
        </a>

        <div class="rounded-2xl border border-line bg-surface p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-dim">Bestellungen heute</p>
            <p class="mt-2 font-display text-3xl font-semibold tabular-nums">{{ $kpis['orders_today'] }}</p>
        </div>

        <a href="{{ route('admin.cancellations') }}" class="rounded-2xl border border-line bg-surface p-5 transition hover:border-accent">
            <p class="text-xs font-semibold uppercase tracking-wide text-dim">Stornos heute</p>
            <p class="mt-2 font-display text-3xl font-semibold tabular-nums {{ $kpis['cancellations_count'] > 0 ? 'text-occupied' : '' }}">
                {{ $kpis['cancellations_count'] }}
            </p>
            <p class="mt-1 text-sm text-dim">{{ $eur($kpis['cancellations_amount']) }}</p>
        </a>

        <a href="{{ route('admin.daily-summary') }}" class="col-span-2 rounded-2xl border p-5 transition hover:border-accent {{ $closing ? 'border-free/40 bg-free-soft' : 'border-line bg-surface' }}">
            <p class="text-xs font-semibold uppercase tracking-wide text-dim">Tagesabschluss</p>
            @if($closing)
                <p class="mt-2 font-display text-xl font-semibold text-free">✓ Abgeschlossen um {{ $closing->closed_at->format('H:i') }}</p>
                <p class="mt-1 text-sm text-dim">{{ $eur($closing->paid_amount) }} abgerechnet</p>
            @else
                <p class="mt-2 font-display text-xl font-semibold">Noch offen</p>
                <p class="mt-1 text-sm text-dim">
                    Letzter Abschluss:
                    {{ $operations['last_closing']?->business_date->format('d.m.Y') ?? 'noch keiner' }}
                </p>
            @endif
        </a>

    </div>

    {{-- Umsatzverlauf --}}
    <div class="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-3">

        <section class="rounded-2xl border border-line bg-surface p-5 lg:col-span-2">
            <div class="mb-4 flex items-baseline justify-between">
                <h2 class="font-display text-lg font-semibold">Umsatz nach Stunde</h2>
                <span class="text-xs text-dim">heute</span>
            </div>

            <div class="flex h-44 items-end gap-1 border-b border-line">
                @foreach($hourlyRevenue as $hour)
                    <div class="group relative flex h-full flex-1 items-end">
                        <div
                            class="w-full rounded-t {{ $hour['current'] ? 'bg-accent' : 'bg-accent/45 group-hover:bg-accent/70' }}"
                            style="height: {{ $hour['value'] > 0 ? max(2, round($hour['value'] / $hourMax * 100)) : 0 }}%"
                        ></div>
                        <div class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-1 hidden -translate-x-1/2 whitespace-nowrap rounded-lg border border-line bg-surface-2 px-2 py-1 text-xs shadow-lg group-hover:block">
                            <span class="text-dim">{{ $hour['label'] }}–{{ sprintf('%02d', (int) $hour['label'] + 1) }} Uhr</span>
                            <span class="ml-1 font-semibold tabular-nums">{{ $eur($hour['value']) }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="mt-1.5 flex gap-1 text-[10px] tabular-nums text-dim">
                @foreach($hourlyRevenue as $hour)
                    <span class="flex-1 text-center {{ $hour['current'] ? 'font-semibold text-fg' : '' }}">
                        {{ ($loop->index % 2 === 0 || $loop->last) ? $hour['label'] : '' }}
                    </span>
                @endforeach
            </div>
        </section>

        <section class="rounded-2xl border border-line bg-surface p-5">
            <div class="mb-4 flex items-baseline justify-between">
                <h2 class="font-display text-lg font-semibold">Letzte 7 Tage</h2>
                <span class="text-xs tabular-nums text-dim">Σ {{ $eur(collect($weeklyRevenue)->sum('value')) }}</span>
            </div>

            <div class="flex h-44 items-end gap-2 border-b border-line">
                @foreach($weeklyRevenue as $day)
                    <div class="group relative flex h-full flex-1 items-end">
                        <div
                            class="w-full rounded-t {{ $day['current'] ? 'bg-accent' : 'bg-accent/45 group-hover:bg-accent/70' }}"
                            style="height: {{ $day['value'] > 0 ? max(2, round($day['value'] / $weekMax * 100)) : 0 }}%"
                        ></div>
                        <div class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-1 hidden -translate-x-1/2 whitespace-nowrap rounded-lg border border-line bg-surface-2 px-2 py-1 text-xs shadow-lg group-hover:block">
                            <span class="text-dim">{{ $day['label'] }} {{ $day['date'] }}</span>
                            <span class="ml-1 font-semibold tabular-nums">{{ $eur($day['value']) }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="mt-1.5 flex gap-2 text-[10px] text-dim">
                @foreach($weeklyRevenue as $day)
                    <span class="flex-1 text-center {{ $day['current'] ? 'font-semibold text-fg' : '' }}">{{ $day['label'] }}</span>
                @endforeach
            </div>
        </section>

    </div>

    {{-- Zahlungsarten, Top-Produkte, Betrieb --}}
    <div class="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-3">

        <section class="rounded-2xl border border-line bg-surface p-5">
            <h2 class="mb-4 font-display text-lg font-semibold">Zahlungsarten</h2>

            @forelse($paymentMethods as $method)
                <div class="mb-3 last:mb-0">
                    <div class="flex items-baseline justify-between text-sm">
                        <span class="font-medium">
                            {{ $method['label'] }}
                            <span class="text-xs font-normal text-dim">· {{ $method['count'] }}×</span>
                        </span>
                        <span class="tabular-nums">{{ $eur($method['total']) }}</span>
                    </div>
                    @unless($method['is_voucher'])
                        <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-surface-2">
                            <div class="h-full rounded-full bg-accent" style="width: {{ round($method['total'] / $methodsTotal * 100) }}%"></div>
                        </div>
                    @else
                        <p class="mt-0.5 text-xs text-dim">nicht im Umsatz enthalten</p>
                    @endunless
                </div>
            @empty
                <p class="text-sm text-dim">Heute noch keine Zahlungen.</p>
            @endforelse
        </section>

        <section class="rounded-2xl border border-line bg-surface p-5">
            <div class="mb-4 flex items-baseline justify-between">
                <h2 class="font-display text-lg font-semibold">Top-Produkte</h2>
                <a href="{{ route('admin.product-reports') }}" class="text-xs text-dim hover:text-accent">Statistik →</a>
            </div>

            @forelse($topProducts as $product)
                <div class="mb-3 last:mb-0">
                    <div class="flex items-baseline justify-between gap-3 text-sm">
                        <span class="truncate font-medium">{{ $product['name'] }}</span>
                        <span class="shrink-0 tabular-nums">
                            {{ $product['quantity'] }}×
                            <span class="text-xs text-dim">· {{ $eur($product['revenue']) }}</span>
                        </span>
                    </div>
                    <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-surface-2">
                        <div class="h-full rounded-full bg-accent" style="width: {{ round($product['quantity'] / $productMax * 100) }}%"></div>
                    </div>
                </div>
            @empty
                <p class="text-sm text-dim">Heute noch nichts verkauft.</p>
            @endforelse
        </section>

        <section class="rounded-2xl border border-line bg-surface p-5">
            <h2 class="mb-4 font-display text-lg font-semibold">Betrieb</h2>

            <dl class="divide-y divide-line text-sm">
                <a href="{{ route('admin.devices') }}" class="flex items-center justify-between py-2.5 first:pt-0 hover:text-accent">
                    <dt class="text-dim">Geräte online</dt>
                    <dd class="tabular-nums font-medium">
                        {{ $operations['devices_online'] }} / {{ $operations['devices_approved'] }}
                    </dd>
                </a>

                @if($operations['devices_pending'] > 0)
                    <a href="{{ route('admin.devices') }}" class="flex items-center justify-between py-2.5 hover:text-accent">
                        <dt class="text-dim">Warten auf Freigabe</dt>
                        <dd class="rounded-full bg-accent/15 px-2 py-0.5 text-xs font-semibold text-accent">
                            {{ $operations['devices_pending'] }} {{ $operations['devices_pending'] === 1 ? 'Gerät' : 'Geräte' }}
                        </dd>
                    </a>
                @endif

                <a href="{{ route('admin.print-jobs') }}" class="flex items-center justify-between py-2.5 hover:text-accent">
                    <dt class="text-dim">Druckjobs in Warteschlange</dt>
                    <dd class="tabular-nums font-medium">{{ $operations['print_jobs_waiting'] }}</dd>
                </a>

                <a href="{{ route('admin.print-jobs') }}" class="flex items-center justify-between py-2.5 hover:text-accent">
                    <dt class="text-dim">Fehlgeschlagene Drucke heute</dt>
                    <dd class="tabular-nums font-medium">
                        @if($operations['print_jobs_failed_today'] > 0)
                            <span class="rounded-full bg-occupied-soft px-2 py-0.5 text-xs font-semibold text-occupied">
                                ⚠ {{ $operations['print_jobs_failed_today'] }}
                            </span>
                        @else
                            0
                        @endif
                    </dd>
                </a>
            </dl>
        </section>

    </div>

    {{-- Server --}}
    <section class="mt-3 rounded-2xl border border-line bg-surface p-5">
        <div class="mb-5 flex flex-col gap-1 sm:flex-row sm:items-baseline sm:justify-between">
            <h2 class="font-display text-lg font-semibold">Serverauslastung</h2>
            <p class="text-xs text-dim">
                {{ $server['hostname'] }}
                · PHP {{ $server['php_version'] }}
                · Laravel {{ $server['laravel_version'] }}
                · Uptime {{ $duration($server['uptime_seconds']) }}
            </p>
        </div>

        @php
            $meters = [
                [
                    'label' => 'CPU',
                    'percent' => $server['cpu']['percent'] ?? null,
                    'detail' => $server['cpu']
                        ? 'Load '.number_format($server['cpu']['load_1'], 2, ',', '').' / '.number_format($server['cpu']['load_5'], 2, ',', '').' / '.number_format($server['cpu']['load_15'], 2, ',', '').' · '.$server['cpu']['cores'].' Kerne'
                        : 'nur auf Linux verfügbar',
                ],
                [
                    'label' => 'Arbeitsspeicher',
                    'percent' => $server['memory']['percent'] ?? null,
                    'detail' => $server['memory']
                        ? $bytes($server['memory']['used']).' von '.$bytes($server['memory']['total'])
                        : 'nur auf Linux verfügbar',
                ],
                [
                    'label' => 'Speicherplatz',
                    'percent' => $server['disk']['percent'] ?? null,
                    'detail' => $server['disk']
                        ? $bytes($server['disk']['used']).' von '.$bytes($server['disk']['total'])
                        : 'nicht verfügbar',
                ],
            ];
        @endphp

        <div class="grid grid-cols-1 gap-5 md:grid-cols-3">
            @foreach($meters as $meter)
                @php $state = $level($meter['percent']); @endphp
                <div>
                    <div class="flex items-baseline justify-between">
                        <span class="text-sm font-medium">{{ $meter['label'] }}</span>
                        <span class="text-xs font-semibold {{ $state['text'] }}">{{ $state['label'] }}</span>
                    </div>
                    <p class="mt-1 font-display text-2xl font-semibold tabular-nums">
                        {{ $meter['percent'] !== null ? $meter['percent'].' %' : '—' }}
                    </p>
                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-surface-2">
                        <div class="h-full rounded-full {{ $state['bar'] }}" style="width: {{ $meter['percent'] ?? 0 }}%"></div>
                    </div>
                    <p class="mt-1.5 text-xs tabular-nums text-dim">{{ $meter['detail'] }}</p>
                </div>
            @endforeach
        </div>

        {{-- Dienste --}}
        @php
            $queuePending = $server['queue']['pending'];
            $queueFailed = $server['queue']['failed'];
            $queueStuck = ($server['queue']['oldest_seconds'] ?? 0) > 120;

            $services = [
                [
                    'label' => 'Datenbank',
                    'ok' => $server['database']['ok'],
                    'value' => $server['database']['ok']
                        ? number_format($server['database']['latency_ms'], 1, ',', '').' ms'
                        : 'nicht erreichbar',
                    'detail' => strtoupper($server['database']['driver']).($server['database']['size'] ? ' · '.$bytes($server['database']['size']) : ''),
                ],
                [
                    'label' => 'Warteschlange',
                    'ok' => ! $queueStuck,
                    'value' => $queuePending === null ? $server['queue']['driver'] : $queuePending.' Jobs',
                    'detail' => $queueStuck
                        ? 'ältester Job seit '.$duration($server['queue']['oldest_seconds'])
                        : 'Worker arbeitet ab',
                ],
                [
                    'label' => 'Fehlgeschlagene Jobs',
                    'ok' => ! $queueFailed,
                    'value' => $queueFailed ?? '—',
                    'detail' => $queueFailed ? 'php artisan queue:failed' : 'keine',
                ],
                [
                    'label' => 'Live-Updates (Reverb)',
                    'ok' => $server['reverb']['ok'] ?? null,
                    'value' => $server['reverb'] === null ? 'deaktiviert' : ($server['reverb']['ok'] ? 'verbunden' : 'nicht erreichbar'),
                    'detail' => $server['reverb'] ? $server['reverb']['host'].':'.$server['reverb']['port'] : '',
                ],
            ];
        @endphp

        <div class="mt-6 grid grid-cols-1 gap-3 border-t border-line pt-5 sm:grid-cols-2 lg:grid-cols-4">
            @foreach($services as $service)
                <div class="flex items-start gap-3 rounded-xl bg-ink-soft p-3">
                    <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-bold
                        {{ $service['ok'] === null ? 'bg-surface-2 text-dim' : ($service['ok'] ? 'bg-free/20 text-free' : 'bg-occupied/20 text-occupied') }}">
                        {{ $service['ok'] === null ? '–' : ($service['ok'] ? '✓' : '!') }}
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs text-dim">{{ $service['label'] }}</p>
                        <p class="font-medium tabular-nums">{{ $service['value'] }}</p>
                        @if($service['detail'])
                            <p class="truncate text-xs text-dim">{{ $service['detail'] }}</p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </section>

</div>
