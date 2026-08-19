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
                <th class="px-4 py-3 text-left font-medium">Self Ordering</th>
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
                    <td class="px-4 py-3">

                        <button type="button" wire:click="toggleSelfOrder({{ $table->id }})" class="rounded-full px-3 py-1 text-xs font-medium transition {{ $table->self_order_enabled ? 'bg-free/15 text-free' : 'bg-occupied/15 text-occupied' }}">
                            {{$table->self_order_enabled ? 'Aktiv' : 'Deaktiviert'}}
                        </button>

                    </td>
                    <td class="px-4 py-3 text-right">
                        <button
                            wire:click="delete({{ $table->id }})"
                            wire:confirm="Tisch wirklich löschen?"
                            class="rounded-full border border-occupied/40 px-3 py-1 text-xs font-medium text-occupied transition hover:bg-occupied/10"
                        >
                            Löschen
                        </button>

                        <button
                            type="button"

                            wire:click="showQr({{ $table->id }})"

                            class="rounded-full border border-line px-3 py-1 text-xs font-medium text-fg transition hover:border-accent hover:text-accent"
                        >
                            QR-Code
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

@if($qrModalOpen && $qrUrl)

    <div
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
    >

        <div
            class="w-full max-w-md rounded-3xl border border-line bg-surface p-6 shadow-2xl"
        >

            <div
                class="mb-5 flex items-start justify-between gap-4"
            >

                <div>

                    <p
                        class="text-xs font-semibold uppercase tracking-wide text-accent"
                    >
                        Self Ordering
                    </p>

                    <h2
                        class="mt-1 font-display text-xl font-semibold"
                    >
                        Tisch {{ $qrTableNumber }}
                    </h2>

                </div>


                <button
                    type="button"

                    wire:click="closeQr"

                    class="flex h-9 w-9 items-center justify-center rounded-full border border-line text-lg text-dim"
                >
                    ×
                </button>

            </div>


            <div
                class="rounded-2xl bg-white p-5"
            >

                <div
                    class="flex justify-center"
                >
                    {!! QrCode::size(260)
                        ->margin(1)
                        ->generate($qrUrl) !!}
                </div>

            </div>


            <p
                class="mt-4 break-all text-center text-xs text-dim"
            >
                {{ $qrUrl }}
            </p>


            <div
                class="mt-5 grid gap-2"
            >

                <a
                    href="{{ $qrUrl }}"
                    target="_blank"

                    class="rounded-xl border border-line px-4 py-3 text-center text-sm font-medium transition hover:border-accent hover:text-accent"
                >
                    Link testen
                </a>


                <button
                    type="button"

                    wire:click="
                        regenerateQr({{ $qrTableId }})
                    "

                    wire:confirm="
                        Wirklich einen neuen QR-Code erzeugen?

                        Der bisherige QR-Code für diesen Tisch
                        funktioniert danach nicht mehr.
                    "

                    class="rounded-xl border border-occupied/40 px-4 py-3 text-sm font-medium text-occupied transition hover:bg-occupied/10"
                >
                    QR-Code neu erzeugen
                </button>

            </div>


            <p
                class="mt-4 text-center text-xs leading-relaxed text-dim"
            >
                Der QR-Code bleibt dauerhaft gültig,
                solange er nicht neu erzeugt wird.
            </p>

        </div>

    </div>

@endif
