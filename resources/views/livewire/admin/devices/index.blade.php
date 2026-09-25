<div>

    <div class="mb-5">
        <h1 class="font-display text-2xl font-semibold tracking-tight">Geräteverwaltung</h1>
        <p class="mt-1 text-sm text-dim">Kassen-Terminals freigeben oder sperren.</p>
    </div>

    <div class="overflow-x-auto rounded-2xl border border-line">
        <table class="w-full text-sm">
            <thead class="bg-surface-2 text-xs uppercase tracking-wide text-dim">
            <tr>
                <th class="px-4 py-3 text-left font-medium">Name</th>
                <th class="px-4 py-3 text-left font-medium">Plattform</th>
                <th class="px-4 py-3 text-left font-medium">Status</th>
                <th class="px-4 py-3 text-right font-medium">Aktionen</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-line">
            @forelse($devices as $device)
                <tr class="transition hover:bg-surface-2/40">
                    <td class="px-4 py-3 font-medium">
                        @if($editingId === $device->id)
                            <div class="flex items-center gap-2">
                                <input
                                    type="text"
                                    wire:model="name"
                                    wire:keydown.enter="saveName"
                                    autofocus
                                    class="w-40 rounded-lg border border-line bg-surface-2 px-2.5 py-1.5 text-sm focus:border-accent focus:outline-none"
                                >
                                <button
                                    wire:click="saveName"
                                    class="rounded-full border border-free/40 px-2.5 py-1 text-xs font-medium text-free transition hover:bg-free/10"
                                >
                                    Speichern
                                </button>
                                <button
                                    wire:click="cancelEditing"
                                    class="rounded-full border border-line px-2.5 py-1 text-xs font-medium text-dim transition hover:border-accent hover:text-accent"
                                >
                                    Abbrechen
                                </button>
                            </div>
                            @error('name')
                                <p class="mt-1 text-xs text-occupied">{{ $message }}</p>
                            @enderror
                        @else
                            <div class="flex items-center gap-2">
                                {{ $device->name }}

                                {{-- Code wie auf der Freigabe-Seite des Geräts angezeigt --}}
                                <span
                                    class="rounded-full bg-surface-2 px-2 py-0.5 font-mono text-xs text-dim"
                                    title="Geräte-Code"
                                >
                                    {{ strtoupper(substr($device->fingerprint, 0, 4)) }}
                                </span>

                                <button
                                    wire:click="startEditing({{ $device->id }})"
                                    class="text-xs font-medium text-dim underline decoration-dotted transition hover:text-accent"
                                >
                                    Umbenennen
                                </button>
                            </div>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-dim">{{ $device->platform }}</td>
                    <td class="px-4 py-3">
                        @php
                            $badge = match($device->status) {
                                \App\Enums\DeviceStatus::Approved => ['bg-free/15 text-free', 'Freigegeben'],
                                \App\Enums\DeviceStatus::Blocked => ['bg-occupied/15 text-occupied', 'Gesperrt'],
                                default => ['bg-accent/15 text-accent', 'Wartet'],
                            };
                        @endphp
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badge[0] }}">
                                {{ $badge[1] }}
                            </span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex justify-end gap-2">
                            @if($device->status !== \App\Enums\DeviceStatus::Approved)
                                <button
                                    wire:click="approve({{ $device->id }})"
                                    class="rounded-full border border-free/40 px-3 py-1 text-xs font-medium text-free transition hover:bg-free/10"
                                >
                                    Freigeben
                                </button>
                            @endif

                            @if($device->status !== \App\Enums\DeviceStatus::Blocked)
                                <button
                                    wire:click="block({{ $device->id }})"
                                    class="rounded-full border border-occupied/40 px-3 py-1 text-xs font-medium text-occupied transition hover:bg-occupied/10"
                                >
                                    Sperren
                                </button>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="px-4 py-8 text-center text-dim">Keine Geräte registriert.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

</div>
