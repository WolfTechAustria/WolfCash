<div>

    {{-- Kopf --}}
    <div class="mb-5">
        <h1 class="font-display text-2xl font-semibold tracking-tight">
            Bestellungen
        </h1>

        <p class="mt-1 text-sm text-dim">
            Übersicht über Bestellungen, Zahlungen und Stornos.
        </p>
    </div>

    {{-- Filter --}}
    <div class="mb-5 rounded-2xl border border-line bg-surface p-4">

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">

            {{-- Zeitraum --}}
            <div>
                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-dim">
                    Zeitraum
                </label>

                <select
                    wire:model.live="period"
                    class="w-full rounded-xl border border-line bg-surface-2 px-3 py-2.5 text-sm focus:border-accent focus:outline-none"
                >
                    <option value="today">Heute</option>
                    <option value="yesterday">Gestern</option>
                    <option value="week">Diese Woche</option>
                    <option value="month">Dieser Monat</option>
                    <option value="all">Alle</option>
                </select>
            </div>

            {{-- Status --}}
            <div>
                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-dim">
                    Status
                </label>

                <select
                    wire:model.live="status"
                    class="w-full rounded-xl border border-line bg-surface-2 px-3 py-2.5 text-sm focus:border-accent focus:outline-none"
                >
                    <option value="all">Alle Status</option>
                    <option value="open">Offen</option>
                    <option value="sent">Boniert</option>
                    <option value="paid">Bezahlt</option>
                    <option value="cancelled">Storniert</option>
                </select>
            </div>

            {{-- Tisch --}}
            <div>
                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-dim">
                    Tisch
                </label>

                <select
                    wire:model.live="tableId"
                    class="w-full rounded-xl border border-line bg-surface-2 px-3 py-2.5 text-sm focus:border-accent focus:outline-none"
                >
                    <option value="">Alle Tische</option>

                    @foreach($tables as $table)
                        <option value="{{ $table->id }}">
                            Tisch {{ $table->number }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Zurücksetzen --}}
            <div class="flex items-end">
                <button
                    type="button"
                    wire:click="resetFilters"
                    class="w-full rounded-xl border border-line px-4 py-2.5 text-sm font-medium text-dim transition hover:border-accent hover:text-accent"
                >
                    Filter zurücksetzen
                </button>
            </div>

        </div>

    </div>

    {{-- Kennzahlen --}}
    <div class="mb-5 grid grid-cols-2 gap-2.5 lg:grid-cols-4">

        <div class="rounded-xl border border-line bg-surface p-3">
            <p class="text-xs font-medium uppercase tracking-wide text-dim">
                Bestellungen
            </p>

            <p class="mt-1 font-display text-xl font-semibold tabular-nums">
                {{ $summary['orders'] }}
            </p>
        </div>

        <div class="rounded-xl border border-line bg-surface p-3">
            <p class="text-xs font-medium uppercase tracking-wide text-dim">
                Ursprünglich
            </p>

            <p class="mt-1 font-display text-xl font-semibold tabular-nums">
                {{ number_format(
                    $summary['gross'],
                    2,
                    ',',
                    '.'
                ) }} €
            </p>
        </div>

        <div class="rounded-xl border border-occupied/40 bg-occupied-soft p-3">
            <p class="text-xs font-medium uppercase tracking-wide text-occupied">
                Storniert
            </p>

            <p class="mt-1 font-display text-xl font-semibold tabular-nums text-occupied">
                {{ number_format(
                    $summary['cancelled'],
                    2,
                    ',',
                    '.'
                ) }} €
            </p>
        </div>

        <div class="rounded-xl border border-free/40 bg-free-soft p-3">
            <p class="text-xs font-medium uppercase tracking-wide text-free">
                Bezahlt
            </p>

            <p class="mt-1 font-display text-xl font-semibold tabular-nums text-free">
                {{ number_format(
                    $summary['paid'],
                    2,
                    ',',
                    '.'
                ) }} €
            </p>
        </div>

    </div>

    {{-- Mobile Karten --}}
    <div class="space-y-3 md:hidden">

        @forelse($orders as $order)

            @php
                $grossAmount = $order->items->sum(
                    fn ($item) =>
                        (float) $item->price
                        * (int) $item->quantity
                );

                $cancelledAmount = $order->items->sum(
                    fn ($item) =>
                        (float) $item->price
                        * (int) $item->cancelled_quantity
                );

                $paidAmount = (float) $order->payments->sum('amount');

                $statusBadge = match($order->status) {
                    'paid' => [
                        'bg-free/15 text-free',
                        'Bezahlt',
                    ],
                    'cancelled' => [
                        'bg-occupied/15 text-occupied',
                        'Storniert',
                    ],
                    'sent' => [
                        'bg-accent/15 text-accent',
                        'Boniert',
                    ],
                    default => [
                        'bg-surface-2 text-dim',
                        'Offen',
                    ],
                };
            @endphp

            <article
                wire:key="mobile-order-{{ $order->id }}"
                class="rounded-2xl border border-line bg-surface p-4"
            >
                <div class="flex items-start justify-between gap-3">

                    <div>
                        <p class="font-display text-lg font-semibold">
                            Bestellung #{{ $order->id }}
                        </p>

                        <p class="mt-0.5 text-sm text-dim">
                            Tisch {{ $order->table?->number ?? '–' }}
                            ·
                            {{ $order->created_at->format('d.m.Y H:i') }}
                        </p>
                    </div>

                    <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-medium {{ $statusBadge[0] }}">
                        {{ $statusBadge[1] }}
                    </span>

                </div>

                <div class="mt-4 grid grid-cols-2 gap-2 text-sm">

                    <div class="rounded-xl bg-surface-2 p-3">
                        <p class="text-xs text-dim">Positionen</p>
                        <p class="mt-1 font-medium">
                            {{ $order->items_count }}
                        </p>
                    </div>

                    <div class="rounded-xl bg-surface-2 p-3">
                        <p class="text-xs text-dim">Verrechenbar</p>
                        <p class="mt-1 font-medium tabular-nums">
                            {{ number_format(
                                $order->total,
                                2,
                                ',',
                                '.'
                            ) }} €
                        </p>
                    </div>

                    <div class="rounded-xl bg-surface-2 p-3">
                        <p class="text-xs text-dim">Bezahlt</p>
                        <p class="mt-1 font-medium tabular-nums text-free">
                            {{ number_format(
                                $paidAmount,
                                2,
                                ',',
                                '.'
                            ) }} €
                        </p>
                    </div>

                    <div class="rounded-xl bg-surface-2 p-3">
                        <p class="text-xs text-dim">Storniert</p>
                        <p class="mt-1 font-medium tabular-nums text-occupied">
                            {{ number_format(
                                $cancelledAmount,
                                2,
                                ',',
                                '.'
                            ) }} €
                        </p>
                    </div>

                </div>

                <div class="mt-3 flex items-center justify-between text-xs text-dim">
                    <span>
                        Ursprünglich:
                        {{ number_format(
                            $grossAmount,
                            2,
                            ',',
                            '.'
                        ) }} €
                    </span>

                    <a href="{{ route('admin.orders.show', $order) }}" class="font-medium text-accent">
                        Details →
                    </a>
                </div>

            </article>

        @empty

            <div class="rounded-2xl border border-line bg-surface px-5 py-10 text-center text-dim">
                Keine Bestellungen für den gewählten Filter gefunden.
            </div>

        @endforelse

    </div>

    {{-- Desktop-Tabelle --}}
    <div class="hidden overflow-x-auto rounded-2xl border border-line md:block">

        <table class="w-full text-sm">

            <thead class="bg-surface-2 text-xs uppercase tracking-wide text-dim">
            <tr>
                <th class="px-4 py-3 text-left font-medium">
                    Bestellung
                </th>

                <th class="px-4 py-3 text-left font-medium">
                    Zeitpunkt
                </th>

                <th class="px-4 py-3 text-left font-medium">
                    Tisch
                </th>

                <th class="px-4 py-3 text-left font-medium">
                    Status
                </th>

                <th class="px-4 py-3 text-right font-medium">
                    Positionen
                </th>

                <th class="px-4 py-3 text-right font-medium">
                    Ursprünglich
                </th>

                <th class="px-4 py-3 text-right font-medium">
                    Storniert
                </th>

                <th class="px-4 py-3 text-right font-medium">
                    Verrechenbar
                </th>

                <th class="px-4 py-3 text-right font-medium">
                    Bezahlt
                </th>

                <th class="px-4 py-3 text-right font-medium">
                    Aktion
                </th>
            </tr>
            </thead>

            <tbody class="divide-y divide-line">

            @forelse($orders as $order)

                @php
                    $grossAmount = $order->items->sum(
                        fn ($item) =>
                            (float) $item->price
                            * (int) $item->quantity
                    );

                    $cancelledAmount = $order->items->sum(
                        fn ($item) =>
                            (float) $item->price
                            * (int) $item->cancelled_quantity
                    );

                    $paidAmount = (float) $order->payments->sum('amount');

                    $statusBadge = match($order->status) {
                        'paid' => [
                            'bg-free/15 text-free',
                            'Bezahlt',
                        ],
                        'cancelled' => [
                            'bg-occupied/15 text-occupied',
                            'Storniert',
                        ],
                        'sent' => [
                            'bg-accent/15 text-accent',
                            'Boniert',
                        ],
                        default => [
                            'bg-surface-2 text-dim',
                            'Offen',
                        ],
                    };
                @endphp

                <tr
                    wire:key="order-{{ $order->id }}"
                    class="transition hover:bg-surface-2/40"
                >
                    <td class="px-4 py-3 font-medium">
                        #{{ $order->id }}
                    </td>

                    <td class="whitespace-nowrap px-4 py-3 tabular-nums">
                        {{ $order->created_at->format('d.m.Y H:i') }}
                    </td>

                    <td class="px-4 py-3">
                        Tisch {{ $order->table?->number ?? '–' }}
                    </td>

                    <td class="px-4 py-3">
                            <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $statusBadge[0] }}">
                                {{ $statusBadge[1] }}
                            </span>
                    </td>

                    <td class="px-4 py-3 text-right tabular-nums">
                        {{ $order->items_count }}
                    </td>

                    <td class="px-4 py-3 text-right tabular-nums text-dim">
                        {{ number_format(
                            $grossAmount,
                            2,
                            ',',
                            '.'
                        ) }} €
                    </td>

                    <td class="px-4 py-3 text-right tabular-nums text-occupied">
                        {{ number_format(
                            $cancelledAmount,
                            2,
                            ',',
                            '.'
                        ) }} €
                    </td>

                    <td class="px-4 py-3 text-right font-medium tabular-nums">
                        {{ number_format(
                            $order->total,
                            2,
                            ',',
                            '.'
                        ) }} €
                    </td>

                    <td class="px-4 py-3 text-right font-medium tabular-nums text-free">
                        {{ number_format(
                            $paidAmount,
                            2,
                            ',',
                            '.'
                        ) }} €
                    </td>

                    <td class="px-4 py-3 text-right">
                        <a
                            href="{{ route('admin.orders.show', $order) }}"
                            class="inline-flex rounded-lg border border-line px-3 py-1.5 text-xs font-medium text-dim transition hover:border-accent hover:text-accent"
                        >
                            Details
                        </a>
                    </td>
                </tr>

            @empty

                <tr>
                    <td
                        colspan="10"
                        class="px-4 py-10 text-center text-dim"
                    >
                        Keine Bestellungen für den gewählten Filter gefunden.
                    </td>
                </tr>

            @endforelse

            </tbody>

        </table>

    </div>

    {{-- Seitennavigation --}}
    @if($orders->hasPages())

        <div class="mt-5">
            {{ $orders->links() }}
        </div>

    @endif

</div>
