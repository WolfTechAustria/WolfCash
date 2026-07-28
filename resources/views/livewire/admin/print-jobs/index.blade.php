<div>

    <div class="mb-5">
        <h1 class="font-display text-2xl font-semibold tracking-tight">Druckjobs</h1>
        <p class="mt-1 text-sm text-dim">Verlauf und Fehlerbehandlung für Bondrucke.</p>
    </div>

    <div class="overflow-x-auto rounded-2xl border border-line">
        <table class="w-full text-sm">
            <thead class="bg-surface-2 text-xs uppercase tracking-wide text-dim">
            <tr>
                <th class="px-4 py-3 text-left font-medium">ID</th>
                <th class="px-4 py-3 text-left font-medium">Zeit</th>
                <th class="px-4 py-3 text-left font-medium">Tisch</th>
                <th class="px-4 py-3 text-left font-medium">Drucker</th>
                <th class="px-4 py-3 text-left font-medium">Typ</th>
                <th class="px-4 py-3 text-left font-medium">Status</th>
                <th class="px-4 py-3 text-left font-medium">Payload</th>
                <th class="px-4 py-3 text-right font-medium">Aktion</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-line">
            @forelse($jobs as $job)
                <tr class="transition hover:bg-surface-2/40">
                    <td class="px-4 py-3 text-dim">#{{ $job->id }}</td>
                    <td class="px-4 py-3 tabular-nums">{{ $job->created_at->format('H:i:s') }}</td>
                    <td class="px-4 py-3">{{ $job->order?->table?->number }}</td>
                    <td class="px-4 py-3 text-dim">{{ $job->printer?->name }}</td>
                    <td class="px-4 py-3 text-dim">{{ $job->type }}</td>
                    <td class="px-4 py-3">
                        @php
                            $badge = match($job->status) {
                                'printed' => ['bg-free/15 text-free', 'Gedruckt'],
                                'failed' => ['bg-occupied/15 text-occupied', 'Fehler'],
                                'printing' => ['bg-accent/15 text-accent', 'Druckt…'],
                                default => ['bg-surface-2 text-dim', 'Ausstehend'],
                            };
                        @endphp
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badge[0] }}">{{ $badge[1] }}</span>
                        @if($job->error_message)
                            <div class="mt-1 max-w-xs text-xs text-occupied">{{ $job->error_message }}</div>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <pre class="max-w-xs overflow-x-auto rounded-lg bg-surface-2 p-2 text-[11px] text-dim">{{ json_encode($job->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex flex-wrap justify-end gap-2">
                            <button
                                wire:click="markPrinted({{ $job->id }})"
                                class="rounded-full border border-free/40 px-3 py-1 text-xs font-medium text-free transition hover:bg-free/10"
                            >
                                Gedruckt
                            </button>
                            <button
                                wire:click="markFailed({{ $job->id }})"
                                class="rounded-full border border-occupied/40 px-3 py-1 text-xs font-medium text-occupied transition hover:bg-occupied/10"
                            >
                                Fehler
                            </button>
                            <button
                                wire:click="retry({{ $job->id }})"
                                class="rounded-full border border-line px-3 py-1 text-xs font-medium text-dim transition hover:border-accent hover:text-accent"
                            >
                                Erneut
                            </button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="px-4 py-8 text-center text-dim">Keine Druckjobs vorhanden.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

</div>
