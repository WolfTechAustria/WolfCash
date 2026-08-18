<div
    x-data="{
        cartOpen: @entangle('cartOpen')
    }"
    class="mx-auto min-h-[calc(100dvh-57px)] max-w-3xl pb-24"
>
    @if($selfOrderId)
        <div
            class="mx-4 mb-4 rounded-2xl border border-accent/30 bg-accent/10 p-4"
        >
            <div
                class="font-semibold text-accent"
            >
                Bestellung vorbereitet
            </div>

            <div
                class="mt-1 text-sm text-dim"
            >
                SelfOrder #{{ $selfOrderId }}
                wurde für die Zahlung vorbereitet.
            </div>

            <div
                class="mt-2 text-xs text-dim"
            >
                Es wurde noch kein Bon erzeugt.
            </div>
        </div>
    @endif
    {{-- Kopfbereich --}}
    <div class="px-4 pb-4 pt-5">

        <p
            class="mb-1 text-xs font-semibold uppercase tracking-wider text-accent"
        >
            Direkt am Tisch bestellen
        </p>

        <div
            class="flex items-end justify-between gap-4"
        >
            <div>
                <h1
                    class="font-display text-2xl font-semibold tracking-tight"
                >
                    Tisch {{ $table->number }}
                </h1>

                @if($table->name)
                    <p
                        class="mt-1 text-sm text-dim"
                    >
                        {{ $table->name }}
                    </p>
                @endif
            </div>

            @if($this->cartQuantity > 0)
                <div
                    class="rounded-full bg-surface px-3 py-1.5 text-sm font-semibold text-accent"
                >
                    {{ number_format(
                        $this->cartTotal,
                        2,
                        ',',
                        '.'
                    ) }} €
                </div>
            @endif
        </div>

    </div>


    {{-- Produktgruppen --}}
    <div
        class="scrollbar-none flex gap-2 overflow-x-auto px-4 pb-2"
    >
        @foreach($groups as $group)

            <button
                type="button"

                wire:key="self-group-{{ $group->id }}"

                wire:click="
                    setGroup({{ $group->id }})
                "

                class="shrink-0 rounded-full px-4 py-2 text-sm font-medium transition
                    {{
                        $activeGroup === $group->id
                            ? 'bg-accent text-accent-ink'
                            : 'bg-surface text-dim'
                    }}"
            >
                {{ $group->name }}
            </button>

        @endforeach
    </div>


    {{-- Kategorien --}}
    <div
        class="scrollbar-none flex gap-2 overflow-x-auto px-4 pb-4"
    >
        @foreach($categories as $category)

            <button
                type="button"

                wire:key="
                    self-category-{{ $category->id }}
                "

                wire:click="
                    setCategory({{ $category->id }})
                "

                class="shrink-0 rounded-full border px-3.5 py-1.5 text-sm font-medium transition
                    {{
                        (int) $activeCategory
                            === (int) $category->id
                                ? 'border-accent text-accent'
                                : 'border-line text-dim'
                    }}"
            >
                {{ $category->name }}
            </button>

        @endforeach
    </div>


    {{-- Produkte --}}
    <div
        class="grid grid-cols-2 gap-3 px-4 sm:grid-cols-3"
    >

        @forelse($products as $product)

            @if($product->isSoldOut())

                <div
                    wire:key="
                        self-product-sold-{{ $product->id }}
                    "

                    class="flex min-h-32 flex-col justify-between rounded-2xl border border-line bg-surface/50 p-4 opacity-50"
                >
                    <div>
                        <div
                            class="font-medium leading-snug"
                        >
                            {{ $product->name }}
                        </div>

                        @if($product->description)
                            <p
                                class="mt-1 line-clamp-2 text-xs leading-relaxed text-dim"
                            >
                                {{ $product->description }}
                            </p>
                        @endif
                    </div>

                    <span
                        class="text-xs font-semibold uppercase tracking-wide text-occupied"
                    >
                        Ausverkauft
                    </span>
                </div>

            @else

                <button
                    type="button"

                    wire:key="
                        self-product-{{ $product->id }}
                    "

                    wire:click="
                        addProduct({{ $product->id }})
                    "

                    class="flex min-h-32 flex-col justify-between rounded-2xl border border-line bg-surface p-4 text-left transition active:scale-[0.97] active:border-accent"
                >
                    <div>
                        <div
                            class="font-medium leading-snug"
                        >
                            {{ $product->name }}
                        </div>

                        @if($product->description)
                            <p
                                class="mt-1 line-clamp-2 text-xs leading-relaxed text-dim"
                            >
                                {{ $product->description }}
                            </p>
                        @endif
                    </div>

                    <div
                        class="mt-4 flex items-end justify-between gap-2"
                    >
                        <span
                            class="font-display text-base font-semibold text-accent"
                        >
                            {{ number_format(
                                $product->price,
                                2,
                                ',',
                                '.'
                            ) }} €
                        </span>

                    </div>

                </button>

            @endif

        @empty

            <div
                class="col-span-full rounded-2xl border border-line bg-surface p-6 text-center text-sm text-dim"
            >
                In dieser Kategorie sind aktuell
                keine Produkte verfügbar.
            </div>

        @endforelse

    </div>


    {{-- Mobile Warenkorbleiste --}}
    @if($this->cartQuantity > 0)

        <button
            type="button"

            wire:click="openCart"

            class="safe-bottom fixed inset-x-0 bottom-0 z-30 mx-auto flex max-w-3xl items-center justify-between gap-4 border-t border-line bg-surface px-4 py-3"
        >
            <div class="text-left">

                <div
                    class="text-xs text-dim"
                >
                    {{ $this->cartQuantity }}
                    Artikel
                </div>

                <div
                    class="font-display font-semibold"
                >
                    Warenkorb
                </div>

            </div>

            <span
                class="rounded-full bg-accent px-4 py-2 font-display text-sm font-semibold text-accent-ink"
            >
                {{ number_format(
                    $this->cartTotal,
                    2,
                    ',',
                    '.'
                ) }} €
            </span>
        </button>

    @endif


    {{-- Overlay --}}
    @if($cartOpen)

        <div
            wire:click="closeCart"

            class="fixed inset-0 z-40 bg-black/60"
        ></div>


        {{-- Warenkorb --}}
        <div
            class="safe-bottom fixed inset-x-0 bottom-0 z-50 mx-auto max-h-[85dvh] max-w-3xl overflow-y-auto rounded-t-3xl border-t border-line bg-surface"
        >

            <div
                class="sticky top-0 flex items-center justify-between border-b border-line bg-surface px-4 py-4"
            >
                <div>
                    <h2
                        class="font-display text-xl font-semibold"
                    >
                        Deine Bestellung
                    </h2>

                    <p
                        class="text-xs text-dim"
                    >
                        Tisch {{ $table->number }}
                    </p>
                </div>

                <button
                    type="button"
                    wire:click="closeCart"
                    class="flex h-9 w-9 items-center justify-center rounded-full border border-line text-dim"
                >
                    ×
                </button>
            </div>


            <div class="space-y-3 p-4">

                @foreach($cart as $productId => $item)

                    <div
                        wire:key="
                            self-cart-{{ $productId }}
                        "

                        class="rounded-2xl border border-line bg-surface-2 p-4"
                    >

                        <div
                            class="flex items-start justify-between gap-3"
                        >

                            <div class="min-w-0">
                                <div
                                    class="font-medium"
                                >
                                    {{ $item['name'] }}
                                </div>

                                <div
                                    class="mt-1 text-sm text-accent"
                                >
                                    {{ number_format(
                                        $item['price'],
                                        2,
                                        ',',
                                        '.'
                                    ) }} €
                                </div>
                            </div>


                            <div
                                class="flex items-center gap-3"
                            >

                                <button
                                    type="button"

                                    wire:click="
                                        removeProduct({{ $productId }})
                                    "

                                    class="flex h-9 w-9 items-center justify-center rounded-full border border-line text-lg"
                                >
                                    −
                                </button>

                                <span
                                    class="min-w-5 text-center font-display font-semibold tabular-nums"
                                >
                                    {{ $item['quantity'] }}
                                </span>

                                <button
                                    type="button"

                                    wire:click="
                                        increaseProduct({{ $productId }})
                                    "

                                    class="flex h-9 w-9 items-center justify-center rounded-full bg-accent text-lg font-semibold text-accent-ink"
                                >
                                    +
                                </button>

                            </div>

                        </div>


                        <div class="mt-3">

                            <label
                                class="mb-1 block text-xs font-medium text-dim"
                            >
                                Anmerkung
                            </label>

                            <input
                                type="text"

                                wire:change="
                                    updateNote(
                                        {{ $productId }},
                                        $event.target.value
                                    )
                                "

                                value="{{ $item['note'] }}"

                                placeholder="z. B. ohne Zwiebel"

                                maxlength="500"

                                class="w-full rounded-xl border border-line bg-ink px-3 py-2.5 text-sm text-fg outline-none transition placeholder:text-dim/60 focus:border-accent"
                            >

                        </div>

                    </div>

                @endforeach

            </div>


            <div
                class="sticky bottom-0 border-t border-line bg-surface p-4"
            >

                <div
                    class="mb-4 flex items-center justify-between"
                >
                    <span
                        class="text-sm text-dim"
                    >
                        Gesamt
                    </span>

                    <span
                        class="font-display text-xl font-semibold text-accent"
                    >
                        {{ number_format(
                            $this->cartTotal,
                            2,
                            ',',
                            '.'
                        ) }} €
                    </span>
                </div>


                {{--
                    Noch KEIN Payment.

                    Diesen Button verdrahten wir
                    in Phase C.
                --}}
                @error('cart')
                <div
                    class="mb-3 rounded-xl border border-red-500/30 bg-red-500/10 px-3 py-2 text-sm text-red-300"
                >
                    {{ $message }}
                </div>
                @enderror

                <button
                    type="button"

                    wire:click="proceedToPayment"

                    wire:loading.attr="disabled"

                    wire:target="proceedToPayment"

                    class="w-full rounded-xl bg-accent px-4 py-3.5 font-semibold text-accent-ink transition active:scale-[0.98] disabled:cursor-wait disabled:opacity-60"
                >
    <span
        wire:loading.remove
        wire:target="proceedToPayment"
    >
        Weiter zur Zahlung
    </span>

                    <span
                        wire:loading
                        wire:target="proceedToPayment"
                    >
        Bestellung wird geprüft …
    </span>
                </button>

                <p
                    class="mt-2 text-center text-xs text-dim"
                >
                    Die Bestellung wird erst nach
                    erfolgreicher Zahlung an Küche
                    oder Bar übermittelt.
                </p>

            </div>

        </div>

    @endif

</div>
