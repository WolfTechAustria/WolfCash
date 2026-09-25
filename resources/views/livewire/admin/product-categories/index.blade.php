<div>

    <div class="mb-5">
        <h1 class="font-display text-2xl font-semibold tracking-tight">
            @if($editingId)
                Produktkategorie bearbeiten
            @else
                Produktkategorien
            @endif
        </h1>
        <p class="mt-1 text-sm text-dim">Kategorien bestimmen Drucker und Arbeitsplatz für Produkte.</p>
    </div>

    <form wire:submit="save" class="mb-6 flex flex-wrap items-end gap-3 rounded-2xl border border-line bg-surface p-4">

        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-dim">Gruppe</label>
            <select wire:model="product_group_id" class="w-44 rounded-lg border border-line bg-surface-2 px-3 py-2 text-sm focus:border-accent focus:outline-none">
                <option value="">Gruppe wählen</option>
                @foreach($groups as $group)
                    <option value="{{ $group->id }}">{{ $group->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-dim">Drucker</label>
            <select wire:model="printer_id" class="w-44 rounded-lg border border-line bg-surface-2 px-3 py-2 text-sm focus:border-accent focus:outline-none">
                <option value="">Drucker wählen</option>
                @foreach($printers as $printer)
                    <option value="{{ $printer->id }}">{{ $printer->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-dim">Arbeitsplatz</label>
            <select wire:model="production_station_id" class="w-44 rounded-lg border border-line bg-surface-2 px-3 py-2 text-sm focus:border-accent focus:outline-none">
                <option value="">Station wählen</option>
                @foreach($stations as $station)
                    <option value="{{ $station->id }}">{{ $station->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-dim">Name</label>
            <input
                type="text"
                wire:model="name"
                placeholder="Kategorie"
                class="w-40 rounded-lg border border-line bg-surface-2 px-3 py-2 text-sm placeholder:text-dim/60 focus:border-accent focus:outline-none"
            >
        </div>

        <button type="submit" class="rounded-lg bg-accent px-5 py-2 text-sm font-semibold text-accent-ink transition hover:bg-accent-strong">
            Speichern
        </button>

    </form>

    @error('delete')
    <div class="mb-4 rounded-lg border border-occupied/40 bg-occupied-soft px-4 py-2.5 text-sm text-occupied">
        {{ $message }}
    </div>
    @enderror

    <div class="mb-3 flex items-center justify-between">
        <p class="text-xs text-dim">Reihenfolge per Ziehen am Griff (⠿) anpassen.</p>

        <div class="flex items-center gap-2">
            <label class="text-xs font-medium text-dim">Filter nach Gruppe</label>
            <select wire:model.live="filterGroupId" class="rounded-lg border border-line bg-surface-2 px-3 py-1.5 text-sm focus:border-accent focus:outline-none">
                <option value="">Alle Gruppen</option>
                @foreach($groups as $group)
                    <option value="{{ $group->id }}">{{ $group->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="overflow-x-auto rounded-2xl border border-line">
        <table class="w-full text-sm">
            <thead class="bg-surface-2 text-xs uppercase tracking-wide text-dim">
            <tr>
                <th class="w-10 px-4 py-3"></th>
                <th class="px-4 py-3 text-left font-medium">Gruppe</th>
                <th class="px-4 py-3 text-left font-medium">Drucker</th>
                <th class="px-4 py-3 text-left font-medium">Station</th>
                <th class="px-4 py-3 text-left font-medium">Name</th>
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
            @forelse($categories as $category)
                <tr wire:key="category-{{ $category->id }}" data-id="{{ $category->id }}" class="transition hover:bg-surface-2/40">
                    <td class="px-4 py-3 text-dim">
                        <span class="drag-handle cursor-grab select-none text-lg leading-none active:cursor-grabbing">⠿</span>
                    </td>
                    <td class="px-4 py-3 text-dim">{{ $category->group?->name }}</td>
                    <td class="px-4 py-3 text-dim">{{ $category->printer?->name }}</td>
                    <td class="px-4 py-3 text-dim">{{ $category->productionStation?->name }}</td>
                    <td class="px-4 py-3 font-medium">{{ $category->name }}</td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex justify-end gap-2">
                            <button
                                wire:click="edit({{ $category->id }})"
                                class="rounded-full border border-line px-3 py-1 text-xs font-medium text-dim transition hover:border-accent hover:text-accent"
                            >
                                Bearbeiten
                            </button>
                            <button
                                wire:click="delete({{ $category->id }})"
                                class="rounded-full border border-occupied/40 px-3 py-1 text-xs font-medium text-occupied transition hover:bg-occupied/10"
                            >
                                Löschen
                            </button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-dim">Noch keine Kategorien angelegt.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

</div>
