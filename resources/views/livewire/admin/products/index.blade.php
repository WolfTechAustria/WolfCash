<div>

    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-display text-2xl font-semibold tracking-tight">
                @if($editingId)
                    Produkt bearbeiten
                @else
                    Neues Produkt
                @endif
            </h1>
            <p class="mt-1 text-sm text-dim">Speisekarten-Artikel verwalten.</p>
        </div>

        <div class="flex items-center gap-2">
            <select wire:model.live="filterCategoryId" class="rounded-lg border border-line bg-surface-2 px-3 py-2 text-sm focus:border-accent focus:outline-none">
                <option value="">Alle Kategorien</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}">
                        {{ $category->group?->name }} → {{ $category->name }}
                    </option>
                @endforeach
            </select>

            <input
                type="text"
                wire:model.live="search"
                placeholder="Produkt suchen…"
                class="w-64 rounded-lg border border-line bg-surface-2 px-3 py-2 text-sm placeholder:text-dim/60 focus:border-accent focus:outline-none"
            >
        </div>
    </div>

    <form wire:submit="save" class="mb-6 grid grid-cols-2 gap-3 rounded-2xl border border-line bg-surface p-5 sm:grid-cols-3 lg:grid-cols-6">

        <div class="col-span-2 flex flex-col gap-1 lg:col-span-2">
            <label class="text-xs font-medium text-dim">Name</label>
            <input
                type="text"
                wire:model="name"
                class="rounded-lg border border-line bg-surface-2 px-3 py-2 text-sm focus:border-accent focus:outline-none"
            >
        </div>

        <div class="col-span-2 flex flex-col gap-1 lg:col-span-2">
            <label class="text-xs font-medium text-dim">Kategorie</label>
            <select wire:model="product_category_id" class="rounded-lg border border-line bg-surface-2 px-3 py-2 text-sm focus:border-accent focus:outline-none">
                <option value="">Kategorie wählen</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}">
                        {{ $category->group?->name }} → {{ $category->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-dim">Preis (€)</label>
            <input
                type="number"
                step="0.01"
                wire:model="price"
                class="rounded-lg border border-line bg-surface-2 px-3 py-2 text-sm focus:border-accent focus:outline-none"
            >
        </div>

        <div class="col-span-2 flex flex-col gap-1 lg:col-span-2">
            <label class="text-xs font-medium text-dim">Druckmodus</label>
            <select wire:model="print_mode" class="rounded-lg border border-line bg-surface-2 px-3 py-2 text-sm focus:border-accent focus:outline-none">
                <option value="grouped">Sammelbon</option>
                <option value="split">Einzelbons</option>
                <option value="no_print">Nicht drucken</option>
            </select>
        </div>

        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-dim">Bestand</label>
            <input
                type="number"
                wire:model="available_quantity"
                class="rounded-lg border border-line bg-surface-2 px-3 py-2 text-sm focus:border-accent focus:outline-none"
            >
        </div>

        <div class="flex items-end gap-2 pb-2.5">
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" wire:model="is_active" class="h-4 w-4 rounded border-line bg-surface-2 accent-accent">
                Aktiv
            </label>
        </div>

        <div class="col-span-2 flex items-end sm:col-span-3 lg:col-span-6">
            <button type="submit" class="rounded-lg bg-accent px-5 py-2 text-sm font-semibold text-accent-ink transition hover:bg-accent-strong">
                Produkt speichern
            </button>
        </div>

    </form>

    <p class="mb-2 text-xs text-dim">Reihenfolge per Ziehen am Griff (⠿) anpassen — wirkt auf die aktuell gefilterte/angezeigte Liste.</p>

    <div class="overflow-x-auto rounded-2xl border border-line">
        <table class="w-full text-sm">
            <thead class="bg-surface-2 text-xs uppercase tracking-wide text-dim">
            <tr>
                <th class="w-10 px-4 py-3"></th>
                <th class="px-4 py-3 text-left font-medium">Name</th>
                <th class="px-4 py-3 text-left font-medium">Gruppe</th>
                <th class="px-4 py-3 text-left font-medium">Kategorie</th>
                <th class="px-4 py-3 text-left font-medium">Drucker</th>
                <th class="px-4 py-3 text-left font-medium">Station</th>
                <th class="px-4 py-3 text-right font-medium">Preis</th>
                <th class="px-4 py-3 text-left font-medium">Druck</th>
                <th class="px-4 py-3 text-right font-medium">Bestand</th>
                <th class="px-4 py-3 text-left font-medium">Status</th>
                <th class="px-4 py-3 text-right font-medium">Aktion</th>
            </tr>
            </thead>
            <tbody
                class="divide-y divide-line"
                x-data
                x-init="Sortable.create($el, {
                    handle: '.drag-handle',
                    animation: 150,
                    onEnd: () => $wire.reorder(Array.from($el.children).map(el => el.dataset.id)),
                })"
            >
            @forelse($products as $product)
                <tr wire:key="product-{{ $product->id }}" data-id="{{ $product->id }}" class="transition hover:bg-surface-2/40">
                    <td class="px-4 py-3 text-dim">
                        <span class="drag-handle cursor-grab select-none text-lg leading-none active:cursor-grabbing">⠿</span>
                    </td>
                    <td class="px-4 py-3 font-medium">{{ $product->name }}</td>
                    <td class="px-4 py-3 text-dim">{{ $product->category?->group?->name }}</td>
                    <td class="px-4 py-3 text-dim">{{ $product->category?->name }}</td>
                    <td class="px-4 py-3 text-dim">{{ $product->category?->printer?->name }}</td>
                    <td class="px-4 py-3 text-dim">{{ $product->category?->productionStation?->name }}</td>
                    <td class="px-4 py-3 text-right font-display tabular-nums">{{ number_format($product->price, 2) }} €</td>
                    <td class="px-4 py-3 text-dim">
                        @if($product->print_mode === 'grouped') Sammelbon @endif
                        @if($product->print_mode === 'split') Einzelbons @endif
                        @if($product->print_mode === 'no_print') Kein Druck @endif
                    </td>
                    <td class="px-4 py-3 text-right tabular-nums">
                        @if($product->available_quantity === -1)
                            <span class="text-dim">Unbegrenzt</span>
                        @else
                            {{ $product->available_quantity }}
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <button
                            wire:click="toggleActive({{ $product->id }})"
                            class="rounded-full px-2.5 py-0.5 text-xs font-medium transition {{ $product->is_active ? 'bg-free/15 text-free hover:bg-free/25' : 'bg-occupied/15 text-occupied hover:bg-occupied/25' }}"
                        >
                            {{ $product->is_active ? 'Aktiv' : 'Inaktiv' }}
                        </button>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex justify-end gap-2">
                            <button
                                wire:click="edit({{ $product->id }})"
                                class="rounded-full border border-line px-3 py-1 text-xs font-medium text-dim transition hover:border-accent hover:text-accent"
                            >
                                Bearbeiten
                            </button>
                            <button
                                wire:click="delete({{ $product->id }})"
                                class="rounded-full border border-occupied/40 px-3 py-1 text-xs font-medium text-occupied transition hover:bg-occupied/10"
                            >
                                Löschen
                            </button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="11" class="px-4 py-8 text-center text-dim">Keine Produkte gefunden.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

</div>
