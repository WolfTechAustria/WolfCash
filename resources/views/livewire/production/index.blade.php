<div wire:poll.15s class="px-4 py-4 sm:px-6">

    <div class="mb-5 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <span class="relative flex h-2.5 w-2.5">
                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-free opacity-60"></span>
                <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-free"></span>
            </span>
            <h1 class="font-display text-2xl font-semibold tracking-tight">Küchenmonitor</h1>
        </div>
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
                                    :done="$unit <= $item['production_completed_quantity']"
                                    complete="completeItemUnit({{ $job->id }},{{ $item['id'] }})"
                                    reopen="reopenItemUnit({{ $job->id }},{{ $item['id'] }})"
                                    :note="$item['note'] ?? null"
                                >1x {{ $item['name'] }}@if($item['quantity'] > 1) ({{ $unit }}/{{ $item['quantity'] }})@endif</x-production.ticket-line>
                            @endfor

                        @else

                            {{-- Gruppenbon: gesamte Menge gemeinsam --}}
                            <x-production.ticket-line
                                wire:key="job-{{ $job->id }}-grouped-item-{{ $item['id'] }}"
                                :done="$item['production_status'] === \App\Models\OrderItem::PRODUCTION_DONE"
                                complete="completeGroupedItem({{ $job->id }},{{ $item['id'] }})"
                                reopen="reopenGroupedItem({{ $job->id }},{{ $item['id'] }})"
                                :note="$item['note'] ?? null"
                            >{{ $item['quantity'] }}x {{ $item['name'] }}</x-production.ticket-line>

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
                        wire:confirm="Den gesamten Bon als fertig markieren?"
                        wire:loading.attr="disabled"
                        wire:target="completeJob({{ $job->id }})"
                        class="min-h-[3.5rem] w-full rounded-xl border-2 border-accent font-sans text-lg font-bold text-accent transition active:scale-[0.99]"
                    >
                        Gesamten Bon fertigstellen
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
