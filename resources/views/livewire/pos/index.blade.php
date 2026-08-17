<div>

    @if(session('success'))
        <div class="fixed left-1/2 top-16 z-[70] w-[calc(100%-2rem)] max-w-md -translate-x-1/2 rounded-2xl border border-free/40 bg-free-soft px-4 py-3 text-sm font-medium text-free shadow-xl">
            {{ session('success') }}
        </div>
    @endif




    @if(! $selectedTable)

        {{-- ===================== TISCHAUSWAHL ===================== --}}

        <div class="mx-auto max-w-5xl px-4 py-5 sm:px-6">


            <div class="mb-5 flex items-center justify-between">

                <h1 class="font-display text-2xl font-semibold tracking-tight">
                    Tischauswahl
                </h1>

                <div class="flex rounded-full border border-line bg-surface p-1">

                    <button
                        type="button"
                        wire:click="setTableSelectionMode('keypad')"
                        class="rounded-full px-4 py-1.5 text-sm font-medium transition
                            {{ $tableSelectionMode === 'keypad'
                                ? 'bg-accent text-accent-ink'
                                : 'text-dim' }}"
                    >
                        Nummer
                    </button>

                    <button
                        type="button"
                        wire:click="setTableSelectionMode('list')"
                        class="rounded-full px-4 py-1.5 text-sm font-medium transition
                            {{ $tableSelectionMode === 'list'
                                ? 'bg-accent text-accent-ink'
                                : 'text-dim' }}"
                    >
                        Liste
                    </button>

                </div>

            </div>

            @if($tableSelectionMode === 'keypad')

                <div class="mx-auto max-w-xs">

                    <div class="mb-4 flex h-20 items-center justify-center rounded-2xl border border-line bg-surface font-display text-4xl font-semibold tabular-nums">
                        {{ $tableNumberInput ?: '–' }}
                    </div>

                    @error('tableNumberInput')
                    <div class="mb-3 rounded-lg bg-occupied-soft px-3 py-2 text-sm text-occupied">
                        {{ $message }}
                    </div>
                    @enderror

                    <div class="grid grid-cols-3 gap-3">

                        @foreach([
                            '7', '8', '9',
                            '4', '5', '6',
                            '1', '2', '3',
                        ] as $number)

                            <button
                                type="button"
                                wire:click="pressTableNumber('{{ $number }}')"
                                class="h-16 rounded-2xl border border-line bg-surface font-display text-2xl font-medium text-fg transition active:scale-95 active:bg-surface-2"
                            >
                                {{ $number }}
                            </button>

                        @endforeach

                        <button
                            type="button"
                            wire:click="deleteLastTableNumber"
                            class="h-16 rounded-2xl border border-line bg-surface text-xl text-dim transition active:scale-95 active:bg-surface-2"
                        >
                            ⌫
                        </button>

                        <button
                            type="button"
                            wire:click="pressTableNumber('0')"
                            class="h-16 rounded-2xl border border-line bg-surface font-display text-2xl font-medium text-fg transition active:scale-95 active:bg-surface-2"
                        >
                            0
                        </button>

                        <button
                            type="button"
                            wire:click="confirmTableNumber"
                            class="h-16 rounded-2xl bg-accent text-lg font-semibold text-accent-ink transition active:scale-95"
                        >
                            OK
                        </button>

                    </div>

                    <button
                        type="button"
                        wire:click="clearTableNumber"
                        class="mt-4 w-full rounded-xl border border-line py-2.5 text-sm font-medium text-dim transition hover:border-occupied hover:text-occupied"
                    >
                        Eingabe löschen
                    </button>

                    <br><br>
                    <button type="button"  class="mt-4 w-full rounded-xl border border-line py-2.5 text-sm font-medium text-dim transition hover:border-occupied hover:text-occupied"  onclick="window.ReactNativeWebView?.postMessage(JSON.stringify({type: 'PING'}))">
                        Native testen
                    </button>

                </div>

            @endif

            @if($tableSelectionMode === 'list')

                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">

                    @foreach($tables as $tableItem)

                        @php
                            $occupied = (bool) $tableItem->openOrder;
                        @endphp

                        <button
                            type="button"
                            wire:key="table-{{ $tableItem->id }}"
                            wire:click="selectTable({{ $tableItem->id }})"
                            class="relative flex min-h-32 flex-col items-center justify-center gap-1 rounded-2xl border-2 p-4 text-center transition active:scale-95
                                {{ $occupied
                                    ? 'border-occupied/70 bg-occupied-soft'
                                    : 'border-free/70 bg-free-soft' }}"
                        >
                            <span
                                class="absolute right-3 top-3 h-2.5 w-2.5 rounded-full
                                    {{ $occupied
                                        ? 'bg-occupied'
                                        : 'bg-free' }}"
                            ></span>

                            <span class="font-display text-3xl font-semibold tabular-nums text-fg">
                                {{ $tableItem->number }}
                            </span>

                            @if($occupied)

                                <span class="rounded-full bg-occupied/20 px-2.5 py-0.5 text-sm font-medium text-occupied">
                                    {{ number_format(
                                        $tableItem->openOrder->total,
                                        2,
                                        ',',
                                        '.'
                                    ) }} €
                                </span>

                            @else

                                <span class="text-sm font-medium text-free">
                                    Frei
                                </span>

                            @endif

                        </button>

                    @endforeach

                </div>

            @endif

        </div>

    @else

        {{-- ===================== BESTELLANSICHT ===================== --}}

        <div
            wire:key="pos-order-wrapper-{{ $selectedTable }}"
            x-data="{ cartOpen: false }"
            class="flex min-h-[calc(100dvh-49px)] flex-col lg:flex-row"
        >

            {{-- Produktbereich --}}
            <div class="flex-1 px-4 pb-28 pt-4 sm:px-6 lg:pb-4">

                <div class="mb-4 flex items-center justify-between">

                    <div class="flex items-center gap-3">

                        <button
                            type="button"
                            wire:click="backToTables"
                            class="flex h-9 w-9 items-center justify-center rounded-full border border-line text-dim transition hover:border-accent hover:text-accent"
                            aria-label="Zurück zur Tischauswahl"
                        >
                            ←
                        </button>

                        <h1 class="font-display text-xl font-semibold tracking-tight">
                            Tisch {{ $table?->number }}
                        </h1>

                    </div>

                    <span class="rounded-full bg-surface px-3 py-1 font-display text-sm font-semibold tabular-nums text-accent">
                        {{ number_format(
                            $this->total,
                            2,
                            ',',
                            '.'
                        ) }} €
                    </span>

                </div>

                {{-- Produktgruppen --}}
                <div class="scrollbar-none mb-2 flex gap-2 overflow-x-auto pb-1">

                    @foreach($groups as $group)

                        <button
                            type="button"
                            wire:key="group-{{ $group->id }}"
                            wire:click="setGroup({{ $group->id }})"
                            class="shrink-0 rounded-full px-4 py-2 text-sm font-medium transition
                                {{ $activeGroup === $group->id
                                    ? 'bg-accent text-accent-ink'
                                    : 'bg-surface text-dim' }}"
                        >
                            {{ $group->name }}
                        </button>

                    @endforeach

                </div>

                {{-- Produktkategorien --}}
                <div class="scrollbar-none mb-4 flex gap-2 overflow-x-auto pb-1">

                    @foreach($categories as $category)

                        <button
                            type="button"
                            wire:key="category-{{ $category->id }}"
                            wire:click="setCategory({{ $category->id }})"
                            class="shrink-0 rounded-full border px-3.5 py-1.5 text-sm font-medium transition
                                {{ (int) $activeCategory === (int) $category->id
                                    ? 'border-accent text-accent'
                                    : 'border-line text-dim' }}"
                        >
                            {{ $category->name }}
                        </button>

                    @endforeach

                </div>

                {{-- Produktkacheln --}}
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">

                    @foreach($products as $product)

                        @if($product->isSoldOut())

                            <div
                                wire:key="product-sold-out-{{ $product->id }}"
                                class="flex h-28 flex-col justify-between rounded-2xl border border-line bg-surface/50 p-3 opacity-50"
                            >
                                <span class="text-sm font-medium">
                                    {{ $product->name }}
                                </span>

                                <span class="text-xs font-semibold uppercase tracking-wide text-occupied">
                                    Ausverkauft
                                </span>
                            </div>

                        @else

                            <button
                                type="button"
                                wire:key="product-{{ $product->id }}"
                                wire:click="addProduct({{ $product->id }})"
                                class="flex h-28 flex-col justify-between rounded-2xl border border-line bg-surface p-3 text-left transition active:scale-[0.97] active:border-accent"
                            >
                                <span class="text-sm font-medium leading-snug">
                                    {{ $product->name }}
                                </span>

                                <span class="font-display text-base font-semibold text-accent">
                                    {{ number_format(
                                        $product->price,
                                        2,
                                        ',',
                                        '.'
                                    ) }} €
                                </span>
                            </button>

                        @endif

                    @endforeach

                </div>

            </div>

            {{-- Mobile Warenkorb-Leiste --}}
            <button
                type="button"
                @click="cartOpen = true"
                class="safe-bottom fixed inset-x-0 bottom-0 z-30 flex items-center justify-between gap-3 border-t border-line bg-surface px-4 py-3 lg:hidden"
            >
                <span class="text-sm font-medium text-dim">
                    {{ collect($cart)->sum('quantity') }}
                    Artikel im Warenkorb
                </span>

                <span class="rounded-full bg-accent px-4 py-1.5 font-display text-sm font-semibold text-accent-ink">
                    {{ number_format(
                        $this->total,
                        2,
                        ',',
                        '.'
                    ) }} € · Öffnen
                </span>
            </button>

            {{-- Mobiler Hintergrund --}}
            <div
                x-show="cartOpen"
                x-cloak
                x-transition:enter="transition-opacity ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition-opacity ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="fixed inset-0 z-40 bg-black/60 lg:hidden"
                @click="cartOpen = false"
            ></div>

            {{-- Warenkorb --}}
            <div
                x-show="cartOpen"
                x-cloak
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="translate-y-full"
                x-transition:enter-end="translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="translate-y-0"
                x-transition:leave-end="translate-y-full"
                class="safe-bottom fixed inset-x-0 bottom-0 z-50 flex max-h-[85dvh] flex-col rounded-t-3xl border-t border-line bg-ink-soft shadow-2xl
                    lg:static lg:flex lg:max-h-none lg:w-96 lg:translate-y-0 lg:rounded-none lg:border-l lg:border-t-0 lg:shadow-none"
                @click.stop
            >

                <div class="flex shrink-0 items-center justify-between border-b border-line px-5 py-4">

                    <h2 class="font-display text-lg font-semibold">
                        Bestellung
                    </h2>

                    <button
                        type="button"
                        @click="cartOpen = false"
                        class="text-dim lg:hidden"
                        aria-label="Schließen"
                    >
                        ✕
                    </button>

                </div>

                <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4">

                    <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-dim">
                        Bereits boniert
                    </h3>

                    @forelse($orderItems as $item)

                        <div
                            wire:key="order-item-{{ $item['id'] }}"
                            class="mb-2 rounded-xl border border-line bg-surface px-3 py-2 text-sm"
                        >
                            <div class="flex items-start justify-between gap-3">

                                <div class="min-w-0 flex-1">

                                    <div class="flex flex-wrap items-center gap-2">

                                        <span
                                            class="font-medium
                                                {{ $item['is_fully_cancelled']
                                                    ? 'text-dim line-through'
                                                    : '' }}"
                                        >
                                            {{ $item['open_quantity'] }}×
                                            {{ $item['name'] }}
                                        </span>

                                        @if($item['cancelled_quantity'] > 0)

                                            <span class="rounded-full bg-occupied-soft px-2 py-0.5 text-xs font-medium text-occupied">
                                                {{ $item['cancelled_quantity'] }}
                                                storniert
                                            </span>

                                        @endif

                                    </div>

                                    @if(! empty($item['note']))

                                        <div class="mt-0.5 text-xs text-dim">
                                            Notiz: {{ $item['note'] }}
                                        </div>

                                    @endif

                                    @if($item['quantity'] !== $item['open_quantity'])

                                        <div class="mt-1 text-xs text-dim">
                                            Ursprünglich boniert:
                                            {{ $item['quantity'] }} Stück
                                        </div>

                                    @endif

                                </div>

                                <div class="flex shrink-0 flex-col items-end gap-2">

                                    <span class="tabular-nums text-dim">
                                        {{ number_format(
                                            $item['open_quantity']
                                            * $item['price'],
                                            2,
                                            ',',
                                            '.'
                                        ) }} €
                                    </span>

                                    @if($item['open_quantity'] > 0)

                                        <button
                                            type="button"
                                            wire:click="openCancellationModal({{ $item['id'] }})"
                                            class="rounded-full border border-occupied/60 px-2.5 py-1 text-xs font-medium text-occupied transition active:scale-95"
                                        >
                                            Stornieren
                                        </button>

                                    @endif

                                </div>

                            </div>
                        </div>

                    @empty

                        <p class="mb-4 text-sm text-dim">
                            Noch keine Bonierungen.
                        </p>

                    @endforelse

                    <h3 class="mb-2 mt-5 text-xs font-semibold uppercase tracking-wide text-dim">
                        Warenkorb
                    </h3>

                    @forelse($cart as $item)

                        <div
                            wire:key="cart-item-{{ $item['id'] }}"
                            class="mb-3 rounded-xl border border-line p-3"
                        >
                            <div class="flex items-start justify-between gap-3">

                                <div class="min-w-0 flex-1">

                                    <p class="truncate font-medium">
                                        {{ $item['name'] }}
                                    </p>

                                    <p class="mt-0.5 tabular-nums text-sm text-dim">
                                        {{ number_format(
                                            $item['price'],
                                            2,
                                            ',',
                                            '.'
                                        ) }} €
                                    </p>

                                </div>

                                <div class="flex items-center gap-2">

                                    <button
                                        type="button"
                                        wire:click="removeProduct({{ $item['id'] }})"
                                        class="flex h-9 w-9 items-center justify-center rounded-full bg-surface-2 text-lg leading-none transition active:scale-95"
                                        aria-label="Menge reduzieren"
                                    >
                                        −
                                    </button>

                                    <span class="w-5 text-center font-display font-semibold tabular-nums">
                                        {{ $item['quantity'] }}
                                    </span>

                                    <button
                                        type="button"
                                        wire:click="increaseProduct({{ $item['id'] }})"
                                        class="flex h-9 w-9 items-center justify-center rounded-full bg-accent text-lg leading-none text-accent-ink transition active:scale-95"
                                        aria-label="Menge erhöhen"
                                    >
                                        +
                                    </button>

                                </div>

                            </div>

                            <input
                                type="text"
                                wire:model="cart.{{ $item['id'] }}.note"
                                placeholder="Notiz, z. B. ohne Zwiebeln"
                                class="mt-2 w-full rounded-lg border border-line bg-surface px-2.5 py-1.5 text-sm placeholder:text-dim/70 focus:border-accent focus:outline-none"
                            >

                        </div>

                    @empty

                        <p class="text-sm text-dim">
                            Noch keine Produkte ausgewählt.
                        </p>

                    @endforelse

                </div>

                <div class="safe-bottom shrink-0 border-t border-line px-5 py-4">

                    <div class="mb-3 flex items-center justify-between">

                        <span class="text-sm font-medium text-dim">
                            Gesamt
                        </span>

                        <span class="font-display text-2xl font-semibold tabular-nums text-accent">
                            {{ number_format(
                                $this->total,
                                2,
                                ',',
                                '.'
                            ) }} €
                        </span>

                    </div>

                    <button
                        type="button"
                        wire:click="bonieren"
                        wire:loading.attr="disabled"
                        wire:target="bonieren"
                        class="mb-2 w-full rounded-xl bg-accent py-3.5 text-base font-semibold text-accent-ink transition active:scale-[0.98] disabled:opacity-60"
                    >
                        <span
                            wire:loading.remove
                            wire:target="bonieren"
                        >
                            Bonieren
                        </span>

                        <span
                            wire:loading
                            wire:target="bonieren"
                        >
                            Wird boniert …
                        </span>
                    </button>

                    <a
                        href="{{ route('pos.checkout', $selectedTable) }}"
                        class="block w-full rounded-xl border border-line py-3 text-center text-sm font-medium text-dim transition hover:border-accent hover:text-accent"
                    >
                        Abrechnen
                    </a>

                </div>

            </div>

        </div>

    @endif

    {{-- ===================== STORNO-MODAL ===================== --}}

    @if($showCancellationModal)

        <div
            class="fixed inset-0 z-[9999] flex items-end justify-center bg-black/80 sm:items-center sm:p-4"
            wire:keydown.escape.window="closeCancellationModal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="cancellation-modal-title"
        >
            <button
                type="button"
                wire:click="closeCancellationModal"
                class="absolute inset-0 z-0 cursor-default"
                aria-label="Storno schließen"
            ></button>

            <div
                class="relative z-10 flex max-h-[calc(100dvh-1rem)] w-full flex-col overflow-hidden rounded-t-3xl border border-line bg-ink-soft shadow-2xl
                    sm:max-h-[calc(100dvh-2rem)] sm:max-w-md sm:rounded-3xl"
            >
                <div class="shrink-0 border-b border-line px-5 py-4">

                    <div class="flex items-start justify-between gap-4">

                        <div class="min-w-0">

                            <p class="text-xs font-semibold uppercase tracking-wide text-occupied">
                                Position stornieren
                            </p>

                            <h2
                                id="cancellation-modal-title"
                                class="mt-1 truncate font-display text-xl font-semibold"
                            >
                                {{ $cancellationItemName }}
                            </h2>

                            <p class="mt-1 text-sm text-dim">
                                Noch {{ $cancellationOpenQuantity }}
                                Stück verrechenbar
                            </p>

                        </div>

                        <button
                            type="button"
                            wire:click="closeCancellationModal"
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-line text-dim transition hover:border-accent hover:text-accent"
                            aria-label="Schließen"
                        >
                            ✕
                        </button>

                    </div>

                </div>

                <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-5 py-4">

                    <div class="mb-5">

                        <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-dim">
                            Stornomenge
                        </label>

                        <div class="flex items-center justify-between rounded-2xl border border-line bg-surface p-2">

                            <button
                                type="button"
                                wire:click="decreaseCancellationQuantity"
                                class="flex h-12 w-12 items-center justify-center rounded-xl bg-surface-2 text-2xl transition active:scale-95"
                                aria-label="Stornomenge reduzieren"
                            >
                                −
                            </button>

                            <span class="font-display text-3xl font-semibold tabular-nums">
                                {{ $cancellationQuantity }}
                            </span>

                            <button
                                type="button"
                                wire:click="increaseCancellationQuantity"
                                class="flex h-12 w-12 items-center justify-center rounded-xl bg-accent text-2xl text-accent-ink transition active:scale-95"
                                aria-label="Stornomenge erhöhen"
                            >
                                +
                            </button>

                        </div>

                        @error('cancellationQuantity')

                        <p class="mt-2 text-sm text-occupied">
                            {{ $message }}
                        </p>

                        @enderror

                    </div>

                    <div>

                        <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-dim">
                            Stornogrund
                        </label>

                        <div class="grid grid-cols-2 gap-2">

                            @foreach([
                                'Falsch boniert',
                                'Kunde storniert',
                                'Nicht verfügbar',
                                'Qualitätsproblem',
                            ] as $reason)

                                <button
                                    type="button"
                                    wire:click="selectCancellationReason('{{ $reason }}')"
                                    class="rounded-xl border px-3 py-2.5 text-left text-sm transition
                                        {{ $cancellationReason === $reason
                                            ? 'border-accent bg-accent/10 text-accent'
                                            : 'border-line bg-surface text-dim' }}"
                                >
                                    {{ $reason }}
                                </button>

                            @endforeach

                        </div>

                        <input
                            type="text"
                            wire:model="cancellationReason"
                            maxlength="255"
                            placeholder="Oder eigenen Grund eingeben"
                            class="mt-3 w-full rounded-xl border border-line bg-surface px-3 py-3 text-sm placeholder:text-dim/70 focus:border-accent focus:outline-none"
                        >

                        @error('cancellationReason')

                        <p class="mt-2 text-sm text-occupied">
                            {{ $message }}
                        </p>

                        @enderror

                    </div>

                </div>

                <div class="safe-bottom shrink-0 border-t border-line bg-ink-soft px-5 pb-5 pt-4">

                    <div class="grid grid-cols-2 gap-3">

                        <button
                            type="button"
                            wire:click="closeCancellationModal"
                            class="rounded-xl border border-line py-3 text-sm font-medium text-dim transition active:scale-[0.98]"
                        >
                            Abbrechen
                        </button>

                        <button
                            type="button"
                            wire:click="confirmCancellation"
                            wire:loading.attr="disabled"
                            wire:target="confirmCancellation"
                            class="rounded-xl bg-occupied py-3 text-sm font-semibold text-white transition active:scale-[0.98] disabled:opacity-60"
                        >
                            <span
                                wire:loading.remove
                                wire:target="confirmCancellation"
                            >
                                Stornieren
                            </span>

                            <span
                                wire:loading
                                wire:target="confirmCancellation"
                            >
                                Wird storniert …
                            </span>
                        </button>

                    </div>

                </div>

            </div>

        </div>

    @endif

</div>


<script>
    window.addEventListener(
        'wolfcash-native-message',
        function (event) {
            const message =
                JSON.parse(event.detail);

            console.log(
                'Native Antwort:',
                message
            );

            if (message.type === 'PONG') {
                alert(
                    'WolfCash Native Bridge funktioniert!'
                );
            }
        }
    );
</script>
