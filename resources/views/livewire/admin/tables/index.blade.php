<div>

    <div class="mb-5">
        <h1 class="font-display text-2xl font-semibold tracking-tight">Tischverwaltung</h1>
        <p class="mt-1 text-sm text-dim">Tische anlegen, die in der Kasse ausgewählt werden können.</p>
    </div>

    <form wire:submit="save" class="mb-6 flex flex-wrap items-end gap-3 rounded-2xl border border-line bg-surface p-4">

        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-dim">Tischnummer</label>
            <input
                type="text"
                wire:model="number"
                class="w-32 rounded-lg border border-line bg-surface-2 px-3 py-2 text-sm focus:border-accent focus:outline-none"
            >
            @error('number')
            <span class="text-xs text-occupied">{{ $message }}</span>
            @enderror
        </div>

        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-dim">Bezeichnung</label>
            <input
                type="text"
                wire:model="name"
                class="w-56 rounded-lg border border-line bg-surface-2 px-3 py-2 text-sm focus:border-accent focus:outline-none"
            >
        </div>

        <button type="submit" class="rounded-lg bg-accent px-5 py-2 text-sm font-semibold text-accent-ink transition hover:bg-accent-strong">
            Tisch anlegen
        </button>

    </form>

    <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-dim">Vorhandene Tische</p>

    <div class="overflow-x-auto rounded-2xl border border-line">
        <table class="w-full text-sm">
            <thead class="bg-surface-2 text-xs uppercase tracking-wide text-dim">
            <tr>
                <th class="px-4 py-3 text-left font-medium">Nummer</th>
                <th class="px-4 py-3 text-left font-medium">Name</th>
                <th class="px-4 py-3 text-left font-medium">Status</th>
                <th class="px-4 py-3 text-right font-medium">Aktion</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-line">
            @forelse($tables as $table)
                <tr class="transition hover:bg-surface-2/40">
                    <td class="px-4 py-3 font-display font-semibold">{{ $table->number }}</td>
                    <td class="px-4 py-3">{{ $table->name }}</td>
                    <td class="px-4 py-3">
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $table->status === 'occupied' ? 'bg-occupied/15 text-occupied' : 'bg-free/15 text-free' }}">
                                {{ $table->status === 'occupied' ? 'Belegt' : 'Frei' }}
                            </span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <button
                            wire:click="delete({{ $table->id }})"
                            wire:confirm="Tisch wirklich löschen?"
                            class="rounded-full border border-occupied/40 px-3 py-1 text-xs font-medium text-occupied transition hover:bg-occupied/10"
                        >
                            Löschen
                        </button>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="px-4 py-8 text-center text-dim">Noch keine Tische angelegt.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

</div>
