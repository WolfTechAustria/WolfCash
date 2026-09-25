<div>

    @if(session('success'))
        <div class="fixed left-1/2 top-16 z-[70] w-[calc(100%-2rem)] max-w-md -translate-x-1/2 rounded-2xl border border-free/40 bg-free-soft px-4 py-3 text-sm font-medium text-free shadow-xl">
            {{ session('success') }}
        </div>
    @endif

    {{-- ===================== BESTELLANSICHT · STATIONÄRE KASSE ===================== --}}

    <div
        wire:key="pos-stationary-wrapper-{{ $table->id }}"
        x-data="{ cartOpen: false }"
        x-on:cart-item-added.window="cartOpen = true"
        class="flex min-h-[calc(100dvh-49px)] flex-col lg:flex-row"
    >

        {{-- Produktbereich --}}
        <div class="flex-1 px-4 pb-28 pt-4 sm:px-6 lg:pb-4">

            <div class="mb-4 flex items-center justify-between">

                <h1 class="font-display text-xl font-semibold tracking-tight">
                    {{ $table->name ?: 'Kasse '.$table->number }}
                </h1>

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

                <button
                    type="button"
                    wire:key="category-all"
                    wire:click="setCategory(null)"
                    class="shrink-0 rounded-full border px-3.5 py-1.5 text-sm font-medium transition
                        {{ is_null($activeCategory)
                            ? 'border-accent text-accent'
                            : 'border-line text-dim' }}"
                >
                    Alle
                </button>

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

                    @if($product->isSoldOut() || $stock[$product->id] === 0)

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
            class="fixed inset-0 z-40 bg-black/60 lg:!hidden"
            @click="cartOpen = false"
        ></div>

        {{--
            Warenkorb: bewusst per Klassen-Transform statt x-show gesteuert.
            Alpines x-show setzt ein inline "display:none", das jede
            responsive Tailwind-Klasse (hier lg:static/lg:flex) übersticht
            und den Warenkorb auf großen Bildschirmen/Tablets unsichtbar und
            unöffenbar machen würde (der einzige Öffnen-Button ist lg:hidden).
        --}}
        <div
            :class="cartOpen ? 'translate-y-0' : 'translate-y-full'"
            class="safe-bottom fixed inset-x-0 bottom-0 z-50 flex max-h-[85dvh] flex-col rounded-t-3xl border-t border-line bg-ink-soft shadow-2xl transition-transform duration-200
                lg:static lg:flex lg:max-h-none lg:w-96 lg:translate-y-0 lg:rounded-none lg:border-l lg:border-t-0 lg:shadow-none lg:transition-none"
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

                                <span
                                    class="font-medium
                                        {{ $item['is_fully_cancelled']
                                            ? 'text-dim line-through'
                                            : '' }}"
                                >
                                    {{ $item['open_quantity'] }}×
                                    {{ $item['name'] }}
                                </span>

                                @if(! empty($item['note']))

                                    <div class="mt-0.5 text-xs text-dim">
                                        Notiz: {{ $item['note'] }}
                                    </div>

                                @endif

                            </div>

                            <span class="shrink-0 tabular-nums text-dim">
                                {{ number_format(
                                    $item['open_quantity']
                                    * $item['price'],
                                    2,
                                    ',',
                                    '.'
                                ) }} €
                            </span>

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

                <label class="mb-3 flex items-center gap-2 text-sm text-dim">
                    <input
                        type="checkbox"
                        wire:model="printIndividually"
                        class="h-4 w-4 rounded border-line bg-surface-2 accent-accent"
                    >
                    Bons einzeln drucken (1 Bon pro Stück)
                </label>

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

                @error('cart')
                <div class="mb-3 rounded-lg bg-occupied-soft px-3 py-2 text-sm text-occupied">
                    {{ $message }}
                </div>
                @enderror

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
                        Bonieren · Bon drucken
                    </span>

                    <span
                        wire:loading
                        wire:target="bonieren"
                    >
                        Wird boniert …
                    </span>
                </button>

                <a
                    href="{{ route('pos.checkout', $table, false) }}"
                    class="block w-full rounded-xl border border-line py-3 text-center text-sm font-medium text-dim transition hover:border-accent hover:text-accent"
                >
                    Abrechnen
                </a>

            </div>

        </div>

    </div>

</div>
