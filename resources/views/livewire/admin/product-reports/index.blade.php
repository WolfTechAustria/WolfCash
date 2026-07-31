<div>

    {{-- Kopf --}}
    <div class="mb-5">

        <h1 class="font-display text-2xl font-semibold tracking-tight">
            Produktauswertung
        </h1>

        <p class="mt-1 text-sm text-dim">
            Verkaufte Mengen, Umsätze und Stornos nach Produkt.
        </p>

    </div>

    {{-- Filter --}}
    <div class="mb-5 rounded-2xl border border-line bg-surface p-4">

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">

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
                    <option value="year">Dieses Jahr</option>
                    <option value="custom">Benutzerdefiniert</option>
                </select>
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-dim">
                    Produktgruppe
                </label>

                <select
                    wire:model.live="group"
                    class="w-full rounded-xl border border-line bg-surface-2 px-3 py-2.5 text-sm focus:border-accent focus:outline-none"
                >
                    <option value="all">Alle Gruppen</option>
                    <option value="Getränke">Getränke</option>
                    <option value="Speisen">Speisen</option>
                </select>
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-dim">
                    Kategorie
                </label>

                <select
                    wire:model.live="categoryId"
                    class="w-full rounded-xl border border-line bg-surface-2 px-3 py-2.5 text-sm focus:border-accent focus:outline-none"
                >
                    <option value="">Alle Kategorien</option>

                    @foreach($categories as $category)
                        <option value="{{ $category->id }}">
                            {{ $category->group }}
                            ·
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-dim">
                    Produktsuche
                </label>

                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Produktname"
                    class="w-full rounded-xl border border-line bg-surface-2 px-3 py-2.5 text-sm placeholder:text-dim/70 focus:border-accent focus:outline-none"
                >
            </div>

        </div>

        @if($period === 'custom')

            <div class="mt-3 grid gap-3 sm:grid-cols-2">

                <div>
                    <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-dim">
                        Von
                    </label>

                    <input
                        type="date"
                        wire:model.live="dateFrom"
                        class="w-full rounded-xl border border-line bg-surface-2 px-3 py-2.5 text-sm focus:border-accent focus:outline-none"
                    >
                </div>

                <div>
                    <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-dim">
                        Bis
                    </label>

                    <input
                        type="date"
                        wire:model.live="dateTo"
                        class="w-full rounded-xl border border-line bg-surface-2 px-3 py-2.5 text-sm focus:border-accent focus:outline-none"
                    >
                </div>

            </div>

        @endif

        <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">

            <p class="text-sm text-dim">
                {{ $selectedFrom->format('d.m.Y') }}
                bis
                {{ $selectedTo->format('d.m.Y') }}
            </p>

            <button
                type="button"
                wire:click="resetFilters"
                class="rounded-xl border border-line px-4 py-2.5 text-sm font-medium text-dim transition hover:border-accent hover:text-accent"
            >
                Filter zurücksetzen
            </button>

        </div>

    </div>

    {{-- Kennzahlen --}}
    <div class="mb-5 grid grid-cols-2 gap-2.5 lg:grid-cols-4">

        <div class="rounded-xl border border-line bg-surface p-3">

            <p class="text-xs font-medium uppercase tracking-wide text-dim">
                Verkaufte Produkte
            </p>

            <p class="mt-1 font-display text-xl font-semibold tabular-nums">
                {{ $summary['products'] }}
            </p>

        </div>

        <div class="rounded-xl border border-line bg-surface p-3">

            <p class="text-xs font-medium uppercase tracking-wide text-dim">
                Verkaufte Stück
            </p>

            <p class="mt-1 font-display text-xl font-semibold tabular-nums">
                {{ $summary['quantity'] }}
            </p>

            <p class="mt-1 text-xs text-dim">
                {{ $summary['ordered_quantity'] }}
                ursprünglich bestellt
            </p>

        </div>

        <div class="rounded-xl border border-free/40 bg-free-soft p-3">

            <p class="text-xs font-medium uppercase tracking-wide text-free">
                Verrechenbarer Umsatz
            </p>

            <p class="mt-1 font-display text-xl font-semibold tabular-nums text-free">
                {{ number_format(
                    $summary['revenue'],
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
                    $summary['cancelled_amount'],
                    2,
                    ',',
                    '.'
                ) }} €
            </p>

            <p class="mt-1 text-xs text-occupied">
                {{ $summary['cancelled_quantity'] }}
                Stück
            </p>

        </div>

    </div>

    {{-- Mobile Karten --}}
    <div class="space-y-3 md:hidden">

        @forelse($rows as $row)

            <article
                wire:key="mobile-product-report-{{ $row['product_id'] ?? $loop->index }}"
                class="rounded-2xl border border-line bg-surface p-4"
            >

                <div class="flex items-start justify-between gap-3">

                    <div class="min-w-0">

                        <p class="truncate font-display text-lg font-semibold">
                            {{ $row['name'] }}
                        </p>

                        <p class="mt-0.5 text-sm text-dim">
                            {{ $row['group'] }}
                            ·
                            {{ $row['category'] }}
                        </p>

                    </div>

                    <span class="shrink-0 rounded-full bg-free/15 px-2.5 py-1 text-xs font-medium text-free">
                        {{ $row['quantity'] }} verkauft
                    </span>

                </div>

                <div class="mt-4 grid grid-cols-2 gap-2">

                    <div class="rounded-xl bg-surface-2 p-3">
                        <p class="text-xs text-dim">
                            Umsatz
                        </p>

                        <p class="mt-1 font-medium tabular-nums text-free">
                            {{ number_format(
                                $row['revenue'],
                                2,
                                ',',
                                '.'
                            ) }} €
                        </p>
                    </div>

                    <div class="rounded-xl bg-surface-2 p-3">
                        <p class="text-xs text-dim">
                            Ø Preis
                        </p>

                        <p class="mt-1 font-medium tabular-nums">
                            {{ number_format(
                                $row['average_price'],
                                2,
                                ',',
                                '.'
                            ) }} €
                        </p>
                    </div>

                    <div class="rounded-xl bg-surface-2 p-3">
                        <p class="text-xs text-dim">
                            Bestellt
                        </p>

                        <p class="mt-1 font-medium tabular-nums">
                            {{ $row['ordered_quantity'] }}
                        </p>
                    </div>

                    <div class="rounded-xl bg-surface-2 p-3">
                        <p class="text-xs text-dim">
                            Storniert
                        </p>

                        <p class="mt-1 font-medium tabular-nums text-occupied">
                            {{ $row['cancelled_quantity'] }}
                            ·
                            {{ number_format(
                                $row['cancelled_amount'],
                                2,
                                ',',
                                '.'
                            ) }} €
                        </p>
                    </div>

                </div>

            </article>

        @empty

            <div class="rounded-2xl border border-line bg-surface px-5 py-10 text-center text-dim">
                Für den gewählten Zeitraum wurden keine Produktverkäufe gefunden.
            </div>

        @endforelse

    </div>

    {{-- Desktop-Tabelle --}}
    <div class="hidden overflow-x-auto rounded-2xl border border-line md:block">

        <table class="w-full min-w-[1000px] text-sm">

            <thead class="bg-surface-2 text-xs uppercase tracking-wide text-dim">
            <tr>

                <th class="px-4 py-3 text-left font-medium">
                    <button
                        type="button"
                        wire:click="sortBy('name')"
                        class="inline-flex items-center gap-1 transition hover:text-accent"
                    >
                        Produkt

                        @if($sort === 'name')
                            <span>
                                    {{ $direction === 'asc' ? '↑' : '↓' }}
                                </span>
                        @endif
                    </button>
                </th>

                <th class="px-4 py-3 text-left font-medium">
                    Kategorie
                </th>

                <th class="px-4 py-3 text-right font-medium">
                    Bestellt
                </th>

                <th class="px-4 py-3 text-right font-medium">
                    <button
                        type="button"
                        wire:click="sortBy('quantity')"
                        class="inline-flex items-center gap-1 transition hover:text-accent"
                    >
                        Verkauft

                        @if($sort === 'quantity')
                            <span>
                                    {{ $direction === 'asc' ? '↑' : '↓' }}
                                </span>
                        @endif
                    </button>
                </th>

                <th class="px-4 py-3 text-right font-medium">
                    <button
                        type="button"
                        wire:click="sortBy('cancelled_quantity')"
                        class="inline-flex items-center gap-1 transition hover:text-accent"
                    >
                        Storniert

                        @if($sort === 'cancelled_quantity')
                            <span>
                                    {{ $direction === 'asc' ? '↑' : '↓' }}
                                </span>
                        @endif
                    </button>
                </th>

                <th class="px-4 py-3 text-right font-medium">
                    Ø Preis
                </th>

                <th class="px-4 py-3 text-right font-medium">
                    <button
                        type="button"
                        wire:click="sortBy('cancelled_amount')"
                        class="inline-flex items-center gap-1 transition hover:text-accent"
                    >
                        Stornowert

                        @if($sort === 'cancelled_amount')
                            <span>
                                    {{ $direction === 'asc' ? '↑' : '↓' }}
                                </span>
                        @endif
                    </button>
                </th>

                <th class="px-4 py-3 text-right font-medium">
                    <button
                        type="button"
                        wire:click="sortBy('revenue')"
                        class="inline-flex items-center gap-1 transition hover:text-accent"
                    >
                        Umsatz

                        @if($sort === 'revenue')
                            <span>
                                    {{ $direction === 'asc' ? '↑' : '↓' }}
                                </span>
                        @endif
                    </button>
                </th>

            </tr>
            </thead>

            <tbody class="divide-y divide-line">

            @forelse($rows as $row)

                <tr
                    wire:key="product-report-{{ $row['product_id'] ?? $loop->index }}"
                    class="transition hover:bg-surface-2/40"
                >

                    <td class="px-4 py-3 font-medium">
                        {{ $row['name'] }}
                    </td>

                    <td class="px-4 py-3">

                        <p>
                            {{ $row['category'] }}
                        </p>

                        <p class="mt-0.5 text-xs text-dim">
                            {{ $row['group'] }}
                        </p>

                    </td>

                    <td class="px-4 py-3 text-right tabular-nums text-dim">
                        {{ $row['ordered_quantity'] }}
                    </td>

                    <td class="px-4 py-3 text-right font-medium tabular-nums text-free">
                        {{ $row['quantity'] }}
                    </td>

                    <td class="px-4 py-3 text-right tabular-nums text-occupied">
                        {{ $row['cancelled_quantity'] }}
                    </td>

                    <td class="px-4 py-3 text-right tabular-nums">
                        {{ number_format(
                            $row['average_price'],
                            2,
                            ',',
                            '.'
                        ) }} €
                    </td>

                    <td class="px-4 py-3 text-right tabular-nums text-occupied">
                        {{ number_format(
                            $row['cancelled_amount'],
                            2,
                            ',',
                            '.'
                        ) }} €
                    </td>

                    <td class="px-4 py-3 text-right font-medium tabular-nums text-free">
                        {{ number_format(
                            $row['revenue'],
                            2,
                            ',',
                            '.'
                        ) }} €
                    </td>

                </tr>

            @empty

                <tr>
                    <td
                        colspan="8"
                        class="px-4 py-10 text-center text-dim"
                    >
                        Für den gewählten Zeitraum wurden keine Produktverkäufe gefunden.
                    </td>
                </tr>

            @endforelse

            </tbody>

            @if($rows->isNotEmpty())

                <tfoot class="border-t border-line bg-surface-2 font-medium">

                <tr>

                    <td
                        colspan="2"
                        class="px-4 py-3"
                    >
                        Gesamt
                    </td>

                    <td class="px-4 py-3 text-right tabular-nums">
                        {{ $summary['ordered_quantity'] }}
                    </td>

                    <td class="px-4 py-3 text-right tabular-nums text-free">
                        {{ $summary['quantity'] }}
                    </td>

                    <td class="px-4 py-3 text-right tabular-nums text-occupied">
                        {{ $summary['cancelled_quantity'] }}
                    </td>

                    <td class="px-4 py-3"></td>

                    <td class="px-4 py-3 text-right tabular-nums text-occupied">
                        {{ number_format(
                            $summary['cancelled_amount'],
                            2,
                            ',',
                            '.'
                        ) }} €
                    </td>

                    <td class="px-4 py-3 text-right tabular-nums text-free">
                        {{ number_format(
                            $summary['revenue'],
                            2,
                            ',',
                            '.'
                        ) }} €
                    </td>

                </tr>

                </tfoot>

            @endif

        </table>

    </div>

</div>
