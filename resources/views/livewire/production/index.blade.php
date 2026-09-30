{{-- keep-alive: auch als Hintergrund-Tab weiter abgleichen --}}
<div wire:poll.15s.keep-alive class="px-4 py-4 sm:px-6">

    {{--
        Verbindungsanzeige: grün = Live über Reverb, rot = getrennt
        (dann gleicht nur das Polling ab). Nach dem Wiederverbinden
        sofort neu laden, weil verpasste Signale nicht nachkommen.
    --}}
    <div
        wire:ignore
        class="mb-5 flex items-center justify-between"
        x-data="{
            live: null,
            init() {
                const attach = (tries = 0) => {
                    const connection = window.Echo?.connector?.pusher?.connection;

                    if (! connection) {
                        if (tries < 50) setTimeout(() => attach(tries + 1), 200);
                        else this.live = false;
                        return;
                    }

                    this.live = connection.state === 'connected';

                    connection.bind('state_change', ({ current }) => {
                        const wasLive = this.live;
                        this.live = current === 'connected';

                        if (this.live && wasLive === false) $wire.$refresh();
                    });
                };

                attach();
            },
        }"
    >
        <div class="flex items-center gap-2">
            <span class="relative flex h-2.5 w-2.5">
                <span x-show="live !== false" class="absolute inline-flex h-full w-full animate-ping rounded-full bg-free opacity-60"></span>
                <span class="relative inline-flex h-2.5 w-2.5 rounded-full" :class="live === false ? 'bg-occupied' : 'bg-free'"></span>
            </span>
            <h1 class="font-display text-2xl font-semibold tracking-tight">Küchenmonitor</h1>
        </div>

        <p x-show="live === false" style="display: none" class="text-sm font-medium text-occupied">
            Offline – aktualisiert alle 15 s
        </p>
    </div>

    {{-- Stationsfilter: große Touch-Ziele --}}
    <div class="scrollbar-none mb-6 flex gap-2.5 overflow-x-auto pb-1">
        <button
            type="button"
            wire:click="selectStation(null)"
            class="min-h-[3rem] shrink-0 rounded-2xl border-2 px-5 text-base font-semibold transition
                {{ $station === null ? 'border-accent bg-accent text-accent-ink' : 'border-line bg-surface text-dim' }}"
        >
            Alle
        </button>

        @foreach($stations as $productionStation)
            <button
                type="button"
                wire:key="station-{{ $productionStation->id }}"
                wire:click="selectStation({{ $productionStation->id }})"
                class="min-h-[3rem] shrink-0 rounded-2xl border-2 px-5 text-base font-semibold transition
                    {{ $station === $productionStation->id ? 'border-accent bg-accent text-accent-ink' : 'border-line bg-surface text-dim' }}"
            >
                {{ $productionStation->name }}
            </button>
        @endforeach
    </div>

    {{-- Offene Bons wie am Bondrucker: Papier nebeneinander an der Leiste --}}
    <div class="flex flex-wrap items-start justify-center gap-6 sm:justify-start">
        @forelse($jobCards as $card)

            @php
                $job = $card['job'];
                $items = $card['items'];
                $rule = str_repeat('-', 32);
            @endphp

            <article
                wire:key="print-job-{{ $job->id }}"
                class="flex w-full max-w-[calc(32ch+2.5rem)] flex-col gap-3 font-mono text-[15px] sm:w-auto"
            >
                <x-print-jobs.paper>
                    <div class="text-center text-[2em] font-bold leading-tight">
                        {{ $job->type === \App\Models\PrintJob::TYPE_STATIONARY_ORDER ? 'Bestellbon' : 'Produktionsbon' }}
                    </div>

                    <div class="mt-[1.45em]">
                        <div class="text-center text-[1.6em] font-bold leading-tight">TISCH {{ $job->order?->table?->number ?? '–' }}</div>

                        @if($job->productionStation)
                            <div>{{ mb_strtoupper($job->productionStation->name) }}</div>
                        @endif

                        <div>{{ $job->created_at->format('d.m.Y H:i') }}</div>

                        @if(! empty($job->payload['origin']))
                            <div class="break-words">Von: {{ $job->payload['origin'] }}</div>
                        @endif

                        <div class="overflow-hidden whitespace-nowrap">{{ $rule }}</div>
                    </div>

                    @forelse($items as $item)

                        @if($item['print_mode'] === \App\Models\Product::PRINT_SPLIT)

                            {{-- Einzelbon: jede Einheit separat --}}
                            @for($unit = 1; $unit <= $item['quantity']; $unit++)
                                <x-production.ticket-line
                                    wire:key="job-{{ $job->id }}-item-{{ $item['id'] }}-unit-{{ $unit }}"
                                    :done="$unit <= $item['completed_quantity']"
                                    complete="completeItemUnit({{ $job->id }},{{ $item['id'] }})"
                                    reopen="reopenItemUnit({{ $job->id }},{{ $item['id'] }})"
                                    :note="$item['note'] ?? null"
                                >1x {{ $item['name'] }}@if($item['quantity'] > 1) ({{ $unit }}/{{ $item['quantity'] }})@endif</x-production.ticket-line>
                            @endfor

                        @elseif($item['quantity'] > 0)

                            {{-- Gruppenbon: gesamte Menge gemeinsam --}}
                            <x-production.ticket-line
                                wire:key="job-{{ $job->id }}-grouped-item-{{ $item['id'] }}"
                                :done="$item['done']"
                                complete="completeGroupedItem({{ $job->id }},{{ $item['id'] }})"
                                reopen="reopenGroupedItem({{ $job->id }},{{ $item['id'] }})"
                                :note="$item['note'] ?? null"
                            >{{ $item['quantity'] }}x {{ $item['name'] }}</x-production.ticket-line>

                        @endif

                        {{-- Storno: nicht mehr zubereiten --}}
                        @if($item['cancelled_quantity'] > 0)
                            <div
                                wire:key="job-{{ $job->id }}-item-{{ $item['id'] }}-cancelled"
                                class="flex gap-[1ch] py-1.5 font-bold text-[#c0262d]"
                            >
                                <span class="shrink-0">[-]</span>
                                <span class="min-w-0 flex-1 whitespace-pre-wrap break-words">STORNO {{ $item['cancelled_quantity'] }}x <span class="line-through">{{ $item['name'] }}</span></span>
                            </div>
                        @endif

                    @empty

                        <div>KEINE POSITIONEN</div>

                    @endforelse

                    <div class="overflow-hidden whitespace-nowrap">{{ $rule }}</div>
                    <div>Bon #{{ $job->id }}</div>
                </x-print-jobs.paper>

                @if($items->isNotEmpty())
                    <button
                        type="button"
                        wire:click="completeJob({{ $job->id }})"
                        @if($card['has_open_items'])
                            wire:confirm="Den gesamten Bon als fertig markieren?"
                        @endif
                        wire:loading.attr="disabled"
                        wire:target="completeJob({{ $job->id }})"
                        class="min-h-[3.5rem] w-full rounded-xl border-2 border-accent font-sans text-lg font-bold text-accent transition active:scale-[0.99]"
                    >
                        {{ $card['has_open_items'] ? 'Gesamten Bon fertigstellen' : 'Bon abschließen' }}
                    </button>
                @endif
            </article>

        @empty

            <div class="w-full rounded-2xl border-2 border-dashed border-line px-6 py-16 text-center">
                <p class="text-lg font-medium text-dim">Keine offenen Bons für diese Station.</p>
            </div>

        @endforelse
    </div>

</div>
