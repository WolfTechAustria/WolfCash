<div>

    <div class="mb-5">
        <h1 class="font-display text-2xl font-semibold tracking-tight">
            @if($editingId)
                Drucker bearbeiten
            @else
                Drucker
            @endif
        </h1>
        <p class="mt-1 text-sm text-dim">Bondrucker verwalten und Druckzeitpunkt festlegen.</p>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-lg border border-free/40 bg-free-soft px-4 py-2.5 text-sm text-free">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="mb-4 rounded-lg border border-occupied/40 bg-occupied-soft px-4 py-2.5 text-sm text-occupied">
            {{ session('error') }}
        </div>
    @endif

    {{-- ===================== DRUCKER-AUTODISCOVERY ===================== --}}

    <div class="mb-6 rounded-2xl border border-line bg-surface p-4">

        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-dim">
            Drucker im Netzwerk suchen
        </h2>

        <form wire:submit="startScan" class="flex flex-wrap items-end gap-3">

            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-dim">IP-Bereich (CIDR)</label>
                <input
                    type="text"
                    wire:model="scanCidr"
                    placeholder="192.168.1.0/24"
                    class="w-52 rounded-lg border border-line bg-surface-2 px-3 py-2 text-sm placeholder:text-dim/60 focus:border-accent focus:outline-none"
                >
                @error('scanCidr')
                <span class="text-xs text-occupied">{{ $message }}</span>
                @enderror
            </div>

            <button
                type="submit"
                wire:loading.attr="disabled"
                wire:target="startScan"
                class="rounded-lg border border-accent/40 px-5 py-2 text-sm font-semibold text-accent transition hover:bg-accent/10 disabled:opacity-60"
            >
                Scan starten
            </button>

        </form>

        @if($activeScan)

            <div class="mt-4">

                @if(in_array($activeScan->status, ['pending', 'running']))

                    <div wire:poll.1500ms class="flex items-center gap-3 text-sm text-dim">
                        <div class="h-2 flex-1 overflow-hidden rounded-full bg-surface-2">
                            <div
                                class="h-full rounded-full bg-accent transition-all"
                                style="width: {{ $activeScan->total_hosts > 0 ? min(100, round($activeScan->scanned_hosts / $activeScan->total_hosts * 100)) : 0 }}%"
                            ></div>
                        </div>
                        <span class="shrink-0 tabular-nums">
                            {{ $activeScan->scanned_hosts }} / {{ $activeScan->total_hosts }}
                        </span>
                    </div>

                @elseif($activeScan->status === 'failed')

                    <p class="text-sm text-occupied">
                        Scan fehlgeschlagen: {{ $activeScan->error_message }}
                    </p>

                @else

                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-dim">
                        {{ count($activeScan->results ?? []) }} Drucker gefunden
                    </p>

                    @forelse($activeScan->results ?? [] as $result)

                        <div class="mb-2 flex items-center justify-between rounded-lg bg-surface-2 px-3 py-2 text-sm">
                            <span>
                                {{ $result['ip'] }}:{{ $result['port'] }}
                                <span class="text-xs text-dim">({{ $result['response_time_ms'] }} ms)</span>
                            </span>

                            @if($result['already_known'] ?? false)
                                <span class="rounded-full bg-surface px-2.5 py-0.5 text-xs font-medium text-dim">
                                    Bereits angelegt
                                </span>
                            @else
                                <button
                                    type="button"
                                    wire:click="useDiscoveredPrinter('{{ $result['ip'] }}', {{ $result['port'] }})"
                                    class="rounded-full border border-accent/40 px-3 py-1 text-xs font-medium text-accent transition hover:bg-accent/10"
                                >
                                    Übernehmen
                                </button>
                            @endif
                        </div>

                    @empty

                        <p class="text-sm text-dim">Keine Drucker im angegebenen Bereich gefunden.</p>

                    @endforelse

                @endif

            </div>

        @endif

    </div>

    <form wire:submit="save" class="mb-6 flex flex-wrap items-end gap-3 rounded-2xl border border-line bg-surface p-4">

        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-dim">Name</label>
            <input
                type="text"
                wire:model="name"
                placeholder="z. B. Küchendrucker"
                class="w-48 rounded-lg border border-line bg-surface-2 px-3 py-2 text-sm placeholder:text-dim/60 focus:border-accent focus:outline-none"
            >
        </div>

        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-dim">IP-Adresse</label>
            <input
                type="text"
                wire:model="ip_address"
                placeholder="192.168.1.50"
                class="w-40 rounded-lg border border-line bg-surface-2 px-3 py-2 text-sm placeholder:text-dim/60 focus:border-accent focus:outline-none"
            >
        </div>

        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-dim">Druckzeitpunkt</label>
            <select
                wire:model="print_trigger"
                class="w-64 rounded-lg border border-line bg-surface-2 px-3 py-2 text-sm focus:border-accent focus:outline-none"
            >
                <option value="immediate">Sofort beim Bonieren</option>
                <option value="on_job_complete">Erst wenn der gesamte Bon fertig ist</option>
                <option value="{{ \App\Models\Printer::PRINT_TRIGGER_ON_ITEM_COMPLETE }}">Sobald eine Position fertig ist</option>
            </select>
            @error('print_trigger')
            <span class="text-xs text-occupied">{{ $message }}</span>
            @enderror
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
                <th class="px-4 py-3 text-left font-medium">IP</th>
                <th class="px-4 py-3 text-left font-medium">Status</th>
                <th class="px-4 py-3 text-right font-medium">Aktion</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-line">
            @forelse($printers as $printer)
                <tr class="transition hover:bg-surface-2/40">
                    <td class="px-4 py-3 font-medium">{{ $printer->name }}</td>
                    <td class="px-4 py-3 text-dim">{{ $printer->ip_address }}</td>
                    <td class="px-4 py-3">
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $printer->is_active ? 'bg-free/15 text-free' : 'bg-occupied/15 text-occupied' }}">
                                {{ $printer->is_active ? 'Aktiv' : 'Inaktiv' }}
                            </span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex flex-wrap justify-end gap-2">
                            <button
                                wire:click="toggle({{ $printer->id }})"
                                class="rounded-full border border-line px-3 py-1 text-xs font-medium text-dim transition hover:border-accent hover:text-accent"
                            >
                                Status
                            </button>
                            <button
                                wire:click="edit({{ $printer->id }})"
                                class="rounded-full border border-line px-3 py-1 text-xs font-medium text-dim transition hover:border-accent hover:text-accent"
                            >
                                Bearbeiten
                            </button>
                            <button
                                wire:click="testPrinter({{ $printer->id }})"
                                class="rounded-full border border-accent/40 px-3 py-1 text-xs font-medium text-accent transition hover:bg-accent/10"
                            >
                                🖨 Testdruck
                            </button>
                            <button
                                wire:click="delete({{ $printer->id }})"
                                class="rounded-full border border-occupied/40 px-3 py-1 text-xs font-medium text-occupied transition hover:bg-occupied/10"
                            >
                                Löschen
                            </button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="px-4 py-8 text-center text-dim">Noch keine Drucker angelegt.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

</div>
