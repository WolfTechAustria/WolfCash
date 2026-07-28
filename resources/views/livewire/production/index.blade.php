<div wire:poll.5s class="px-4 py-4 sm:px-6">

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

    @forelse($jobCards as $card)

        @php
            $job = $card['job'];
            $items = $card['items'];
        @endphp

        <article
            wire:key="print-job-{{ $job->id }}"
            class="mb-5 rounded-2xl border-2 border-line bg-surface p-5"
        >
            <header class="mb-4 flex items-start justify-between gap-4 border-b border-line pb-4">
                <div>
                    <h2 class="font-display text-2xl font-semibold">
                        Tisch {{ $job->order?->table?->number ?? '–' }}
                    </h2>

                    @if($job->productionStation)
                        <p class="mt-0.5 text-sm text-dim">{{ $job->productionStation->name }}</p>
                    @endif
                </div>

                <div class="text-right">
                    <p class="font-display text-xl font-semibold tabular-nums text-accent">
                        {{ $job->created_at->format('H:i') }}
                    </p>
                    <p class="text-sm text-dim">Bon #{{ $job->id }}</p>
                </div>
            </header>

            @forelse($items as $item)

                @if($item['print_mode'] === \App\Models\Product::PRINT_SPLIT)

                    {{-- Einzelbon: jede Einheit separat --}}
                    @for($unit = 1; $unit <= $item['quantity']; $unit++)
                        @php
                            $unitIsDone = $unit <= $item['production_completed_quantity'];
                        @endphp

                        <div
                            wire:key="job-{{ $job->id }}-item-{{ $item['id'] }}-unit-{{ $unit }}"
                            class="flex items-center justify-between gap-4 border-t border-line py-3.5 {{ $unitIsDone ? 'opacity-45' : '' }}"
                        >
                            <div class="min-w-0">
                                <p class="text-lg font-semibold leading-snug">
                                    1× {{ $item['name'] }}
                                    @if($item['quantity'] > 1)
                                        <span class="text-sm font-normal text-dim">({{ $unit }}/{{ $item['quantity'] }})</span>
                                    @endif
                                </p>

                                @if(!empty($item['note']))
                                    <p class="mt-1 font-semibold text-accent">Hinweis: {{ $item['note'] }}</p>
                                @endif
                            </div>

                            @if(! $unitIsDone)
                                <button
                                    type="button"
                                    wire:click="completeItemUnit({{ $job->id }},{{ $item['id'] }})"
                                    wire:loading.attr="disabled"
                                    class="min-h-[3.25rem] min-w-[7rem] shrink-0 rounded-xl bg-accent text-base font-bold text-accent-ink transition active:scale-95"
                                >
                                    Fertig
                                </button>
                            @else
                                <button
                                    type="button"
                                    wire:click="reopenItemUnit({{ $job->id }},{{ $item['id'] }})"
                                    wire:loading.attr="disabled"
                                    title="Fertig-Markierung zurücknehmen"
                                    class="min-h-[3.25rem] min-w-[7rem] shrink-0 rounded-xl border-2 border-free bg-free-soft text-base font-bold text-free transition active:scale-95"
                                >
                                    ✓ Fertig
                                </button>
                            @endif
                        </div>

                    @endfor
                @else
                    {{-- Gruppenbon: gesamte Menge gemeinsam --}}

                    @php
                        $itemIsDone = $item['production_status'] === \App\Models\OrderItem::PRODUCTION_DONE;
                    @endphp

                    <div
                        wire:key="job-{{ $job->id }}-grouped-item-{{ $item['id'] }}"
                        class="flex items-center justify-between gap-4 border-t border-line py-3.5 {{ $itemIsDone ? 'opacity-45' : '' }}"
                    >
                        <div class="min-w-0">
                            <p class="text-lg font-semibold leading-snug">
                                {{ $item['quantity'] }}× {{ $item['name'] }}
                            </p>

                            @if(!empty($item['note']))
                                <p class="mt-1 font-semibold text-accent">Hinweis: {{ $item['note'] }}</p>
                            @endif
                        </div>

                        @if(! $itemIsDone)
                            <button
                                type="button"
                                wire:click="completeGroupedItem({{ $job->id }},{{ $item['id'] }})"
                                wire:loading.attr="disabled"
                                class="min-h-[3.25rem] min-w-[7.5rem] shrink-0 rounded-xl bg-accent text-base font-bold text-accent-ink transition active:scale-95"
                            >
                                Fertig
                            </button>
                        @else
                            <button
                                type="button"
                                wire:click="reopenGroupedItem({{ $job->id }},{{ $item['id'] }})"
                                wire:loading.attr="disabled"
                                title="Fertig-Markierung zurücknehmen"
                                class="min-h-[3.25rem] min-w-[7.5rem] shrink-0 rounded-xl border-2 border-free bg-free-soft text-base font-bold text-free transition active:scale-95"
                            >
                                ✓ Fertig
                            </button>
                        @endif
                    </div>

                @endif

            @empty

                <div class="border-t border-line py-4 text-dim">
                    Dieser Bon enthält keine gültigen Positionen.
                </div>

            @endforelse

            @if($items->isNotEmpty())
                <button
                    type="button"
                    wire:click="completeJob({{ $job->id }})"
                    wire:confirm="Den gesamten Bon als fertig markieren?"
                    wire:loading.attr="disabled"
                    wire:target="completeJob({{ $job->id }})"
                    class="mt-5 min-h-[3.5rem] w-full rounded-xl border-2 border-accent text-lg font-bold text-accent transition active:scale-[0.99]"
                >
                    Gesamten Bon fertigstellen
                </button>
            @endif
        </article>

    @empty

        <div class="rounded-2xl border-2 border-dashed border-line px-6 py-16 text-center">
            <p class="text-lg font-medium text-dim">Keine offenen Bons für diese Station.</p>
        </div>

    @endforelse

</div>
