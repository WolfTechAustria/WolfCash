<div>

    <div class="mb-5">
        <h1 class="font-display text-2xl font-semibold tracking-tight">Druckjobs</h1>
        <p class="mt-1 text-sm text-dim">Verlauf und Fehlerbehandlung für Bondrucke. Zeile anklicken für die Bon-Vorschau.</p>
    </div>

    <div class="overflow-x-auto rounded-2xl border border-line">
        <table class="w-full text-sm">
            <thead class="bg-surface-2 text-xs uppercase tracking-wide text-dim">
            <tr>
                <th class="w-8 py-3 pl-4"></th>
                <th class="px-4 py-3 text-left font-medium">ID</th>
                <th class="px-4 py-3 text-left font-medium">Zeit</th>
                <th class="px-4 py-3 text-left font-medium">Tisch</th>
                <th class="px-4 py-3 text-left font-medium">Drucker</th>
                <th class="px-4 py-3 text-left font-medium">Typ</th>
                <th class="px-4 py-3 text-left font-medium">Inhalt</th>
                <th class="px-4 py-3 text-left font-medium">Status</th>
                <th class="px-4 py-3 text-right font-medium">Aktion</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-line">
            @forelse($jobs as $job)
                @php
                    $isOpen = in_array($job->id, $expanded, true);
                    $payload = $job->payload ?? [];
                    $items = $payload['items'] ?? [];
                    $unitCount = collect($items)->sum(fn ($item) => max(1, (int) ($item['quantity'] ?? 1)));

                    $typeLabel = match($job->type) {
                        \App\Models\PrintJob::TYPE_RECEIPT => 'Zahlungsbeleg',
                        \App\Models\PrintJob::TYPE_STATIONARY_ORDER => 'Selbstbedienung',
                        \App\Models\PrintJob::TYPE_PRODUCTION => 'Produktion',
                        default => $job->type,
                    };

                    $summary = $job->type === \App\Models\PrintJob::TYPE_RECEIPT
                        ? number_format((float) ($payload['amount'] ?? 0), 2, ',', '.').' €'
                        : collect($items)->take(2)->map(fn ($item) => max(1, (int) ($item['quantity'] ?? 1)).'× '.($item['name'] ?? '?'))->join(', ')
                            .(count($items) > 2 ? ' …' : '');

                    $badge = match($job->status) {
                        'printed' => ['bg-free/15 text-free', 'Gedruckt'],
                        'failed' => ['bg-occupied/15 text-occupied', 'Fehler'],
                        'printing' => ['bg-accent/15 text-accent', 'Druckt…'],
                        default => ['bg-surface-2 text-dim', 'Ausstehend'],
                    };

                    $outputStatus = fn (string $status) => match($status) {
                        'printed' => ['text-free', 'gedruckt'],
                        'failed' => ['text-occupied', 'Fehler'],
                        'printing' => ['text-accent', 'druckt…'],
                        default => ['text-dim', 'ausstehend'],
                    };

                    /*
                     * Nachdrucke/Stornobons sind weitere Ausgaben am selben
                     * Job — der letzte wird in der Zeile hervorgehoben.
                     */
                    $lastOutput = $job->outputs->last();
                    $showLastOutput = $lastOutput
                        && ($job->outputs->count() > 1 || $lastOutput->created_at->diffInSeconds($job->created_at, true) > 5);
                @endphp

                <tr
                    wire:key="job-{{ $job->id }}"
                    wire:click="toggle({{ $job->id }})"
                    class="cursor-pointer transition hover:bg-surface-2/40 {{ $isOpen ? 'bg-surface-2/40' : '' }}"
                >
                    <td class="py-3 pl-4 text-dim">
                        <svg class="size-4 transition-transform {{ $isOpen ? 'rotate-90' : '' }}" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.17 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z" clip-rule="evenodd"/>
                        </svg>
                    </td>
                    <td class="px-4 py-3 text-dim">#{{ $job->id }}</td>
                    <td class="px-4 py-3 tabular-nums">
                        {{ $job->created_at->format('H:i:s') }}
                        @if($showLastOutput)
                            <div class="whitespace-nowrap text-xs text-accent">
                                {{ \App\Livewire\Admin\PrintJobs\Index::outputLabel($lastOutput) }}
                                {{ $lastOutput->created_at->isSameDay($job->created_at) ? $lastOutput->created_at->format('H:i:s') : $lastOutput->created_at->format('d.m. H:i') }}
                            </div>
                        @endif
                    </td>
                    <td class="px-4 py-3">{{ $job->order?->table?->number }}</td>
                    <td class="px-4 py-3 text-dim">{{ $job->printer?->name }}</td>
                    <td class="px-4 py-3 text-dim">
                        {{ $typeLabel }}
                        @if($job->productionStation)
                            <span class="text-xs">· {{ $job->productionStation->name }}</span>
                        @endif
                    </td>
                    <td class="max-w-xs px-4 py-3">
                        <div class="truncate">{{ $summary ?: '–' }}</div>
                        @if($items !== [])
                            <div class="text-xs text-dim">{{ $unitCount }} Stk. ·{{ count($items) }} {{ count($items) === 1 ? 'Position' : 'Positionen' }}</div>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <span class="whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badge[0] }}">{{ $badge[1] }}</span>
                        @if($showLastOutput)
                            @php $lastStatus = $outputStatus($lastOutput->status); @endphp
                            <div class="mt-1 whitespace-nowrap text-xs text-dim">
                                {{ $job->outputs->count() }} {{ $job->outputs->count() === 1 ? 'Ausdruck' : 'Ausdrucke' }}
                                · letzter <span class="{{ $lastStatus[0] }}">{{ $lastStatus[1] }}</span>
                            </div>
                        @endif
                        @if($job->error_message)
                            <div class="mt-1 max-w-xs text-xs text-occupied {{ $isOpen ? '' : 'truncate' }}">{{ $job->error_message }}</div>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex flex-wrap justify-end gap-2">
                            <button
                                wire:click.stop="markPrinted({{ $job->id }})"
                                class="rounded-full border border-free/40 px-3 py-1 text-xs font-medium text-free transition hover:bg-free/10"
                            >
                                Gedruckt
                            </button>
                            <button
                                wire:click.stop="markFailed({{ $job->id }})"
                                class="rounded-full border border-occupied/40 px-3 py-1 text-xs font-medium text-occupied transition hover:bg-occupied/10"
                            >
                                Fehler
                            </button>
                            <button
                                wire:click.stop="retry({{ $job->id }})"
                                class="rounded-full border border-line px-3 py-1 text-xs font-medium text-dim transition hover:border-accent hover:text-accent"
                            >
                                Erneut
                            </button>
                        </div>
                    </td>
                </tr>

                @if($isOpen)
                    <tr wire:key="job-{{ $job->id }}-preview" class="bg-surface-2/40">
                        <td colspan="9" class="px-4 pb-6 pt-2">
                            @php $documents = $previews[$job->id] ?? []; @endphp

                            @if(is_string($documents))
                                <div class="rounded-lg border border-occupied/40 bg-occupied/10 px-3 py-2 text-xs text-occupied">
                                    Vorschau konnte nicht erzeugt werden: {{ $documents }}
                                </div>
                            @else
                                <div class="flex flex-wrap items-start gap-6">
                                    @foreach($documents as $document)
                                        <x-print-jobs.paper-slip
                                            :document="$document"
                                            :label="count($documents) > 1 ? 'Bon '.$loop->iteration.'/'.count($documents) : null"
                                        />
                                    @endforeach
                                </div>
                            @endif

                            @if($job->outputs->isNotEmpty())
                                <div class="mt-5">
                                    <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-dim">Ausdrucke</h3>

                                    <div class="overflow-x-auto rounded-xl border border-line">
                                        <table class="w-full text-xs">
                                            <tbody class="divide-y divide-line">
                                            @foreach($job->outputs->reverse() as $output)
                                                @php $status = $outputStatus($output->status); @endphp
                                                <tr wire:key="job-{{ $job->id }}-output-{{ $output->id }}">
                                                    <td class="px-3 py-2 text-dim">#{{ $output->id }}</td>
                                                    <td class="px-3 py-2 tabular-nums">{{ $output->created_at->format('d.m. H:i:s') }}</td>
                                                    <td class="px-3 py-2">{{ \App\Livewire\Admin\PrintJobs\Index::outputLabel($output) }}</td>
                                                    <td class="px-3 py-2 text-dim">{{ $output->printer?->name }}</td>
                                                    <td class="px-3 py-2">
                                                        <span class="{{ $status[0] }}">{{ $status[1] }}</span>
                                                        @if($output->error_message)
                                                            <div class="text-occupied">{{ $output->error_message }}</div>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endif

                            <details class="mt-5 text-xs text-dim">
                                <summary class="cursor-pointer select-none hover:text-fg">Rohdaten (JSON)</summary>
                                <pre class="mt-2 max-h-80 overflow-auto rounded-lg bg-surface-2 p-3 text-[11px]">{{ json_encode($job->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                            </details>
                        </td>
                    </tr>
                @endif
            @empty
                <tr>
                    <td colspan="9" class="px-4 py-8 text-center text-dim">Keine Druckjobs vorhanden.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

</div>
