<div>

    <div class="mb-5">
        <h1 class="font-display text-2xl font-semibold tracking-tight">
            @if($editingId)
                Produktionsstation bearbeiten
            @else
                Produktionsstationen
            @endif
        </h1>
        <p class="mt-1 text-sm text-dim">Arbeitsplätze in der Küche, z. B. Grill oder Kaltküche.</p>
    </div>

    <form wire:submit="save" class="mb-6 flex flex-wrap items-end gap-3 rounded-2xl border border-line bg-surface p-4">

        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-dim">Name</label>
            <input
                type="text"
                wire:model="name"
                placeholder="z. B. Grillstation"
                class="w-56 rounded-lg border border-line bg-surface-2 px-3 py-2 text-sm placeholder:text-dim/60 focus:border-accent focus:outline-none"
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

    <div class="overflow-x-auto rounded-2xl border border-line">
        <table class="w-full text-sm">
            <thead class="bg-surface-2 text-xs uppercase tracking-wide text-dim">
            <tr>
                <th class="px-4 py-3 text-left font-medium">Name</th>
                <th class="px-4 py-3 text-right font-medium">Aktion</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-line">
            @forelse($stations as $station)
                <tr class="transition hover:bg-surface-2/40">
                    <td class="px-4 py-3 font-medium">{{ $station->name }}</td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex justify-end gap-2">
                            <button
                                wire:click="edit({{ $station->id }})"
                                class="rounded-full border border-line px-3 py-1 text-xs font-medium text-dim transition hover:border-accent hover:text-accent"
                            >
                                Bearbeiten
                            </button>
                            <button
                                wire:click="delete({{ $station->id }})"
                                class="rounded-full border border-occupied/40 px-3 py-1 text-xs font-medium text-occupied transition hover:bg-occupied/10"
                            >
                                Löschen
                            </button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="2" class="px-4 py-8 text-center text-dim">Noch keine Stationen angelegt.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

</div>
