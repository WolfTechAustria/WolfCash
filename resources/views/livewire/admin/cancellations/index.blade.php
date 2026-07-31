<div>

    {{-- Kopf --}}
    <div class="mb-5">

        <h1 class="font-display text-2xl font-semibold tracking-tight">
            Stornos
        </h1>

        <p class="mt-1 text-sm text-dim">
            Übersicht über alle stornierten Positionen und Teilmengen.
        </p>

    </div>

    {{-- Filter --}}
    <div class="mb-5 rounded-2xl border border-line bg-surface p-4">

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">

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

            <div>
                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-dim">
                    Produkt
                </label>

                <select
                    wire:model.live="productId"
                    class="w-full rounded-xl border border-line bg-surface-2 px-3 py-2.5 text-sm focus:border-accent focus:outline-none"
                >
                    <option value="">Alle Produkte</option>

                    @foreach($products as $product)
                        <option value="{{ $product->id }}">
                            {{ $product->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-dim">
                    Mitarbeiter
                </label>

                <select
                    wire:model.live="userId"
                    class="w-full rounded-xl border border-line bg-surface-2 px-3 py-2.5 text-sm focus:border-accent focus:outline-none"
                >
                    <option value="">Alle Mitarbeiter</option>

                    @foreach($users as $user)
                        @php
                            $userName = trim(
                                ($user->first_name ?? '')
                                .' '.
                                ($user->last_name ?? '')
                            );

                            if ($userName === '') {
                                $userName = $user->name
                                    ?? 'Benutzer #'.$user->id;
                            }
                        @endphp

                        <option value="{{ $user->id }}">
                            {{ $userName }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-dim">
                    Suche
                </label>

                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Produkt oder Grund"
                    class="w-full rounded-xl border border-line bg-surface-2 px-3 py-2.5 text-sm placeholder:text-dim/70 focus:border-accent focus:outline-none"
                >
            </div>

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
    <div class="mb-5 grid grid-cols-2 gap-2.5 md:grid-cols-3">

        <div class="rounded-xl border border-line bg-surface p-3">

            <p class="text-xs font-medium uppercase tracking-wide text-dim">
                Stornovorgänge
            </p>

            <p class="mt-1 font-display text-xl font-semibold tabular-nums">
                {{ $summary['count'] }}
            </p>

        </div>

        <div class="rounded-xl border border-line bg-surface p-3">

            <p class="text-xs font-medium uppercase tracking-wide text-dim">
                Stornierte Stück
            </p>

            <p class="mt-1 font-display text-xl font-semibold tabular-nums">
                {{ $summary['quantity'] }}
            </p>

        </div>

        <div class="col-span-2 rounded-xl border border-occupied/40 bg-occupied-soft p-3 md:col-span-1">

            <p class="text-xs font-medium uppercase tracking-wide text-occupied">
                Stornierter Wert
            </p>

            <p class="mt-1 font-display text-xl font-semibold tabular-nums text-occupied">
                {{ number_format(
                    $summary['amount'],
                    2,
                    ',',
                    '.'
                ) }} €
            </p>

        </div>

    </div>

    {{-- Mobile Ansicht --}}
    <div class="space-y-3 md:hidden">

        @forelse($cancellations as $cancellation)

            @php
                $item = $cancellation->orderItem;
                $order = $item?->order;
                $user = $cancellation->cancelledByUser;

                $userName = $user
                    ? trim(
                        ($user->first_name ?? '')
                        .' '.
                        ($user->last_name ?? '')
                    )
                    : '';

                if ($userName === '' && $user) {
                    $userName = $user->name
                        ?? 'Benutzer #'.$user->id;
                }

                $amount =
                    (float) ($item?->price ?? 0)
                    * (int) $cancellation->quantity;
            @endphp

            <article
                wire:key="mobile-cancellation-{{ $cancellation->id }}"
                class="rounded-2xl border border-line bg-surface p-4"
            >
                <div class="flex items-start justify-between gap-3">

                    <div class="min-w-0">

                        <p class="truncate font-medium">
                            {{ $item?->product?->name
                                ?? 'Unbekanntes Produkt' }}
                        </p>

                        <p class="mt-0.5 text-sm text-dim">
                            Tisch {{ $order?->table?->number ?? '–' }}
                            · Bestellung #{{ $order?->id ?? '–' }}
                        </p>

                    </div>

                    <span class="shrink-0 rounded-full bg-occupied/15 px-2.5 py-1 text-xs font-medium text-occupied">
                        {{ $cancellation->quantity }} Stück
                    </span>

                </div>

                <div class="mt-4 rounded-xl bg-surface-2 p-3">

                    <p class="text-xs font-medium uppercase tracking-wide text-dim">
                        Grund
                    </p>

                    <p class="mt-1 text-sm">
                        {{ $cancellation->reason }}
                    </p>

                </div>

                <div class="mt-3 flex items-end justify-between gap-3">

                    <div class="text-xs text-dim">

                        <p>
                            {{ $cancellation->cancelled_at?->format(
                                'd.m.Y H:i'
                            ) }}
                        </p>

                        <p class="mt-0.5">
                            {{ $userName !== ''
                                ? $userName
                                : 'Nicht zugeordnet' }}
                        </p>

                    </div>

                    <div class="text-right">

                        <p class="font-display text-lg font-semibold tabular-nums text-occupied">
                            {{ number_format(
                                $amount,
                                2,
                                ',',
                                '.'
                            ) }} €
                        </p>

                        @if($order)
                            <a
                                href="{{ route(
                                    'admin.orders.show',
                                    $order
                                ) }}"
                                class="mt-1 inline-block text-xs font-medium text-accent"
                            >
                                Bestellung öffnen →
                            </a>
                        @endif

                    </div>

                </div>

            </article>

        @empty

            <div class="rounded-2xl border border-line bg-surface px-5 py-10 text-center text-dim">
                Keine Stornos für den gewählten Filter gefunden.
            </div>

        @endforelse

    </div>

    {{-- Desktop-Tabelle --}}
    <div class="hidden overflow-x-auto rounded-2xl border border-line md:block">

        <table class="w-full min-w-[1000px] text-sm">

            <thead class="bg-surface-2 text-xs uppercase tracking-wide text-dim">
            <tr>
                <th class="px-4 py-3 text-left font-medium">
                    Zeitpunkt
                </th>

                <th class="px-4 py-3 text-left font-medium">
                    Bestellung
                </th>

                <th class="px-4 py-3 text-left font-medium">
                    Tisch
                </th>

                <th class="px-4 py-3 text-left font-medium">
                    Produkt
                </th>

                <th class="px-4 py-3 text-right font-medium">
                    Menge
                </th>

                <th class="px-4 py-3 text-right font-medium">
                    Wert
                </th>

                <th class="px-4 py-3 text-left font-medium">
                    Grund
                </th>

                <th class="px-4 py-3 text-left font-medium">
                    Mitarbeiter
                </th>

                <th class="px-4 py-3 text-right font-medium">
                    Aktion
                </th>
            </tr>
            </thead>

            <tbody class="divide-y divide-line">

            @forelse($cancellations as $cancellation)

                @php
                    $item = $cancellation->orderItem;
                    $order = $item?->order;
                    $user = $cancellation->cancelledByUser;

                    $userName = $user
                        ? trim(
                            ($user->first_name ?? '')
                            .' '.
                            ($user->last_name ?? '')
                        )
                        : '';

                    if ($userName === '' && $user) {
                        $userName = $user->name
                            ?? 'Benutzer #'.$user->id;
                    }

                    $amount =
                        (float) ($item?->price ?? 0)
                        * (int) $cancellation->quantity;
                @endphp

                <tr
                    wire:key="cancellation-{{ $cancellation->id }}"
                    class="transition hover:bg-surface-2/40"
                >
                    <td class="whitespace-nowrap px-4 py-3 tabular-nums">
                        {{ $cancellation->cancelled_at?->format(
                            'd.m.Y H:i'
                        ) }}
                    </td>

                    <td class="px-4 py-3 font-medium">
                        #{{ $order?->id ?? '–' }}
                    </td>

                    <td class="px-4 py-3">
                        Tisch {{ $order?->table?->number ?? '–' }}
                    </td>

                    <td class="px-4 py-3">
                        {{ $item?->product?->name
                            ?? 'Unbekanntes Produkt' }}
                    </td>

                    <td class="px-4 py-3 text-right tabular-nums text-occupied">
                        {{ $cancellation->quantity }}
                    </td>

                    <td class="px-4 py-3 text-right font-medium tabular-nums text-occupied">
                        {{ number_format(
                            $amount,
                            2,
                            ',',
                            '.'
                        ) }} €
                    </td>

                    <td class="max-w-xs px-4 py-3">
                            <span class="line-clamp-2">
                                {{ $cancellation->reason }}
                            </span>
                    </td>

                    <td class="px-4 py-3">
                        {{ $userName !== ''
                            ? $userName
                            : 'Nicht zugeordnet' }}
                    </td>

                    <td class="px-4 py-3 text-right">

                        @if($order)
                            <a
                                href="{{ route(
                                        'admin.orders.show',
                                        $order
                                    ) }}"
                                class="inline-flex rounded-lg border border-line px-3 py-1.5 text-xs font-medium text-dim transition hover:border-accent hover:text-accent"
                            >
                                Bestellung
                            </a>
                        @endif

                    </td>

                </tr>

            @empty

                <tr>
                    <td
                        colspan="9"
                        class="px-4 py-10 text-center text-dim"
                    >
                        Keine Stornos für den gewählten Filter gefunden.
                    </td>
                </tr>

            @endforelse

            </tbody>

        </table>

    </div>

    @if($cancellations->hasPages())

        <div class="mt-5">
            {{ $cancellations->links() }}
        </div>

    @endif

</div>
