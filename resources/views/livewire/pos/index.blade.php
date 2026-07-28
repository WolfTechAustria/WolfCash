<div>

    @if(!$selectedTable)

        {{-- ===================== TISCHAUSWAHL ===================== --}}

        <div class="mx-auto max-w-5xl px-4 py-5 sm:px-6">

            <div class="mb-5 flex items-center justify-between">
                <h1 class="font-display text-2xl font-semibold tracking-tight">Tischauswahl</h1>

                <div class="flex rounded-full border border-line bg-surface p-1">
                    <button
                        wire:click="setTableSelectionMode('keypad')"
                        class="rounded-full px-4 py-1.5 text-sm font-medium transition {{ $tableSelectionMode === 'keypad' ? 'bg-accent text-accent-ink' : 'text-dim' }}"
                    >
                        Nummer
                    </button>
                    <button
                        wire:click="setTableSelectionMode('list')"
                        class="rounded-full px-4 py-1.5 text-sm font-medium transition {{ $tableSelectionMode === 'list' ? 'bg-accent text-accent-ink' : 'text-dim' }}"
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

                        @foreach(['7','8','9','4','5','6','1','2','3'] as $number)
                            <button
                                wire:click="pressTableNumber('{{ $number }}')"
                                class="h-16 rounded-2xl border border-line bg-surface font-display text-2xl font-medium text-fg transition active:scale-95 active:bg-surface-2"
                            >
                                {{ $number }}
                            </button>
                        @endforeach

                        <button
                            wire:click="deleteLastTableNumber"
                            class="h-16 rounded-2xl border border-line bg-surface text-xl text-dim transition active:scale-95 active:bg-surface-2"
                        >
                            ⌫
                        </button>

                        <button
                            wire:click="pressTableNumber('0')"
                            class="h-16 rounded-2xl border border-line bg-surface font-display text-2xl font-medium text-fg transition active:scale-95 active:bg-surface-2"
                        >
                            0
                        </button>

                        <button
                            wire:click="confirmTableNumber"
                            class="h-16 rounded-2xl bg-accent text-lg font-semibold text-accent-ink transition active:scale-95"
                        >
                            OK
                        </button>

                    </div>

                    <button
                        wire:click="clearTableNumber"
                        class="mt-4 w-full rounded-xl border border-line py-2.5 text-sm font-medium text-dim transition hover:border-occupied hover:text-occupied"
                    >
                        Eingabe löschen
                    </button>

                </div>

            @endif

            @if($tableSelectionMode === 'list')

                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">

                    @foreach($tables as $table)

                        @php $occupied = (bool) $table->openOrder; @endphp

                        <button
                            wire:click="selectTable({{ $table->id }})"
                            class="relative flex min-h-32 flex-col items-center justify-center gap-1 rounded-2xl border-2 p-4 text-center transition active:scale-95
                                {{ $occupied
                                    ? 'border-occupied/70 bg-occupied-soft'
                                    : 'border-free/70 bg-free-soft' }}"
                        >
                            <span class="absolute right-3 top-3 h-2.5 w-2.5 rounded-full {{ $occupied ? 'bg-occupied' : 'bg-free' }}"></span>

                            <span class="font-display text-3xl font-semibold tabular-nums text-fg">
                                {{ $table->number }}
                            </span>

                            @if($occupied)
                                <span class="rounded-full bg-occupied/20 px-2.5 py-0.5 text-sm font-medium text-occupied">
                                    {{ number_format($table->openOrder->total, 2) }} €
                                </span>
                            @else
                                <span class="text-sm font-medium text-free">Frei</span>
                            @endif
                        </button>

                    @endforeach

                </div>

            @endif

        </div>

    @else

        {{-- ===================== BESTELLANSICHT ===================== --}}

        <div x-data="{ cartOpen: false }" class="flex min-h-[calc(100dvh-49px)] flex-col lg:flex-row">

            {{-- Produktbereich --}}
            <div class="flex-1 px-4 pb-28 pt-4 sm:px-6 lg:pb-4">

                <div class="mb-4 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <button
                            wire:click="backToTables"
                            class="flex h-9 w-9 items-center justify-center rounded-full border border-line text-dim transition hover:border-accent hover:text-accent"
                            aria-label="Zurück zur Tischauswahl"
                        >
                            ←
                        </button>
                        <h1 class="font-display text-xl font-semibold tracking-tight">
                            Tisch {{ $table->number }}
                        </h1>
                    </div>

                    <span class="rounded-full bg-surface px-3 py-1 font-display text-sm font-semibold tabular-nums text-accent">
                        {{ number_format($this->total, 2) }} €
                    </span>
                </div>

                {{-- Produktgruppen --}}
                <div class="scrollbar-none mb-2 flex gap-2 overflow-x-auto pb-1">
                    @foreach($groups as $group)
                        <button
                            wire:click="setGroup({{ $group->id }})"
                            class="shrink-0 rounded-full px-4 py-2 text-sm font-medium transition
                                {{ $activeGroup === $group->id ? 'bg-accent text-accent-ink' : 'bg-surface text-dim' }}"
                        >
                            {{ $group->name }}
                        </button>
                    @endforeach
                </div>

                {{-- Produktkategorien --}}
                <div class="scrollbar-none mb-4 flex gap-2 overflow-x-auto pb-1">
                    @foreach($categories as $category)
                        <button
                            wire:click="setCategory({{ $category->id }})"
                            class="shrink-0 rounded-full border px-3.5 py-1.5 text-sm font-medium transition
                                {{ (string) $activeCategory === (string) $category->id
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

                            <div class="flex h-28 flex-col justify-between rounded-2xl border border-line bg-surface/50 p-3 opacity-50">
                                <span class="text-sm font-medium">{{ $product->name }}</span>
                                <span class="text-xs font-semibold uppercase tracking-wide text-occupied">Ausverkauft</span>
                            </div>

                        @else

                            <button
                                wire:click="addProduct({{ $product->id }})"
                                class="flex h-28 flex-col justify-between rounded-2xl border border-line bg-surface p-3 text-left transition active:scale-[0.97] active:border-accent"
                            >
                                <span class="text-sm font-medium leading-snug">{{ $product->name }}</span>
                                <span class="font-display text-base font-semibold text-accent">
                                    {{ number_format($product->price, 2) }} €
                                </span>
                            </button>

                        @endif
                    @endforeach

                </div>

            </div>

            {{-- Warenkorb: mobile Sticky-Leiste + Drawer, Desktop feste Seitenspalte --}}

            <button
                @click="cartOpen = true"
                class="safe-bottom fixed inset-x-0 bottom-0 z-30 flex items-center justify-between gap-3 border-t border-line bg-surface px-4 py-3 lg:hidden"
            >
                <span class="text-sm font-medium text-dim">
                    {{ collect($cart)->sum('quantity') }} Artikel im Warenkorb
                </span>
                <span class="rounded-full bg-accent px-4 py-1.5 font-display text-sm font-semibold text-accent-ink">
                    {{ number_format($this->total, 2) }} € · Öffnen
                </span>
            </button>

            <div
                x-show="cartOpen"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                x-cloak
                class="fixed inset-0 z-40 bg-black/60 lg:hidden"
                @click="cartOpen = false"
            ></div>

            <div
                x-show="cartOpen"
                x-transition:enter="transition ease-out duration-250"
                x-transition:enter-start="translate-y-full"
                x-transition:enter-end="translate-y-0"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="translate-y-0"
                x-transition:leave-end="translate-y-full"
                x-cloak
                class="safe-bottom fixed inset-x-0 bottom-0 z-40 flex max-h-[85dvh] flex-col rounded-t-3xl border-t border-line bg-ink-soft
                    lg:static lg:z-auto lg:max-h-none lg:w-96 lg:translate-y-0 lg:rounded-none lg:border-l lg:border-t-0"
            >

                <div class="flex items-center justify-between border-b border-line px-5 py-4">
                    <h2 class="font-display text-lg font-semibold">Bestellung</h2>
                    <button @click="cartOpen = false" class="text-dim lg:hidden" aria-label="Schließen">✕</button>
                </div>

                <div class="flex-1 overflow-y-auto px-5 py-4">

                    <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-dim">Bereits boniert</h3>

                    @forelse($orderItems as $item)
                        <div class="mb-2 flex items-center justify-between rounded-xl bg-surface px-3 py-2 text-sm">
                            <div>
                                <span class="font-medium">{{ $item['quantity'] }}× {{ $item['name'] }}</span>
                                @if(!empty($item['note']))
                                    <div class="text-xs text-dim">Notiz: {{ $item['note'] }}</div>
                                @endif
                            </div>
                            <span class="tabular-nums text-dim">{{ number_format($item['price'], 2) }} €</span>
                        </div>
                    @empty
                        <p class="mb-4 text-sm text-dim">Noch keine Bonierungen.</p>
                    @endforelse

                    <h3 class="mb-2 mt-5 text-xs font-semibold uppercase tracking-wide text-dim">Warenkorb</h3>

                    @forelse($cart as $item)
                        <div class="mb-3 rounded-xl border border-line p-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-medium">{{ $item['name'] }}</p>
                                    <p class="mt-0.5 tabular-nums text-sm text-dim">{{ number_format($item['price'], 2) }} €</p>
                                </div>

                                <div class="flex items-center gap-2">
                                    <button
                                        wire:click="removeProduct({{ $item['id'] }})"
                                        class="flex h-9 w-9 items-center justify-center rounded-full bg-surface-2 text-lg leading-none transition active:scale-95"
                                    >
                                        −
                                    </button>
                                    <span class="w-5 text-center font-display font-semibold tabular-nums">{{ $item['quantity'] }}</span>
                                    <button
                                        wire:click="increaseProduct({{ $item['id'] }})"
                                        class="flex h-9 w-9 items-center justify-center rounded-full bg-accent text-lg leading-none text-accent-ink transition active:scale-95"
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
                        <p class="text-sm text-dim">Noch keine Produkte ausgewählt.</p>
                    @endforelse

                </div>

                <div class="safe-bottom border-t border-line px-5 py-4">
                    <div class="mb-3 flex items-center justify-between">
                        <span class="text-sm font-medium text-dim">Gesamt</span>
                        <span class="font-display text-2xl font-semibold tabular-nums text-accent">
                            {{ number_format($this->total, 2) }} €
                        </span>
                    </div>

                    <button
                        wire:click="bonieren"
                        class="mb-2 w-full rounded-xl bg-accent py-3.5 text-base font-semibold text-accent-ink transition active:scale-[0.98]"
                    >
                        Bonieren
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

</div>
