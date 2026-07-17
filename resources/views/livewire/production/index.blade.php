<div wire:poll.5s>

    <div
        style="
            display:flex;
            flex-wrap:wrap;
            gap:10px;
            margin-bottom:24px;
        "
    >
        <button
            type="button"
            wire:click="selectStation(null)"
            style="
                padding:10px 16px;
                border-radius:8px;
                border:1px solid #aaa;
                font-weight:{{ $station === null ? '700' : '400' }};
            "
        >
            Alle
        </button>

        @foreach($stations as $productionStation)

            <button
                type="button"
                wire:key="station-{{ $productionStation->id }}"
                wire:click="selectStation({{ $productionStation->id }})"
                style="
                    padding:10px 16px;
                    border-radius:8px;
                    border:1px solid #aaa;
                    font-weight:{{
                        $station === $productionStation->id
                            ? '700'
                            : '400'
                    }};
                "
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
            style="
                border:2px solid #333;
                border-radius:12px;
                padding:20px;
                margin-bottom:20px;
            "
        >
            <header
                style="
                    display:flex;
                    justify-content:space-between;
                    align-items:flex-start;
                    gap:15px;
                    margin-bottom:18px;
                "
            >
                <div>
                    <h2 style="margin:0;">
                        Tisch {{ $job->order?->table?->number ?? '–' }}
                    </h2>

                    @if($job->productionStation)
                        <div style="margin-top:4px;">
                            {{ $job->productionStation->name }}
                        </div>
                    @endif
                </div>

                <div style="text-align:right;">
                    <strong>
                        {{ $job->created_at->format('H:i') }}
                    </strong>

                    <div>
                        Bon #{{ $job->id }}
                    </div>
                </div>
            </header>

            @forelse($items as $item)

                @if($item['print_mode'] === \App\Models\Product::PRINT_SPLIT)

                    {{--Einzelbon: jede Einheit separat anzeigen --}}
                    @for($unit = 1; $unit <= $item['quantity']; $unit++)
                        @php
                            $unitIsDone = $unit <= $item['production_completed_quantity'];
                        @endphp

                        <div wire:key="job-{{ $job->id }}-item-{{ $item['id'] }}-unit-{{ $unit }}" style="display:flex;justify-content:space-between;align-items:center;gap:15px;padding:15px 0;border-top:1px solid #ddd;opacity:{{ $unitIsDone ? '0.55' : '1' }};">

                            <div>
                                <div style="font-size:20px;">
                                    <strong>1×</strong>
                                    {{ $item['name'] }}

                                @if($item['quantity'] > 1)
                                    <small>
                                        ({{ $unit }}/{{ $item['quantity'] }})
                                    </small>
                                @endif
                            </div>

                                @if(!empty($item['note']))
                                    <div style="margin-top:5px;font-weight:600;">
                                        Hinweis: {{ $item['note'] }}
                                    </div>
                                @endif
                        </div>

                            @if(! $unitIsDone)

                                <button type="button" wire:click="completeItemUnit({{ $job->id }},{{ $item['id'] }})" wire:loading.attr="disabled" style=" min-width:100px; padding:12px 18px; font-size:16px; font-weight:700;">
                                    Fertig
                                </button>

                            @else

                                <button
                                    type="button"
                                    wire:click="
                            reopenItemUnit(
                                {{ $job->id }},
                                {{ $item['id'] }}
                            )
                        "
                                    wire:loading.attr="disabled"
                                    title="Fertig-Markierung zurücknehmen"
                                    style="
                            min-width:100px;
                            padding:12px 18px;
                            font-size:16px;
                            font-weight:700;
                        "
                                >
                                    ✓ Fertig
                                </button>

                            @endif
                        </div>

                    @endfor
                @else
                    {{-- Gruppenbon: gesamte Menge gemeinsam anzeigen --}}

                    @php
                        $itemIsDone =
                            $item['production_status']
                            === \App\Models\OrderItem::PRODUCTION_DONE;
                    @endphp

                    <div
                        wire:key="
                job-{{ $job->id }}
                -grouped-item-{{ $item['id'] }}
            "
                        style="
                display:flex;
                justify-content:space-between;
                align-items:center;
                gap:15px;
                padding:15px 0;
                border-top:1px solid #ddd;
                opacity:{{ $itemIsDone ? '0.55' : '1' }};
            "
                    >
                        <div>
                            <div style="font-size:20px;">
                                <strong>
                                    {{ $item['quantity'] }}×
                                </strong>

                                {{ $item['name'] }}
                            </div>

                            @if(!empty($item['note']))
                                <div
                                    style="
                            margin-top:5px;
                            font-weight:600;
                        "
                                >
                                    Hinweis: {{ $item['note'] }}
                                </div>
                            @endif
                        </div>

                        @if(! $itemIsDone)

                            <button
                                type="button"
                                wire:click="
                        completeGroupedItem(
                            {{ $job->id }},
                            {{ $item['id'] }}
                        )
                    "
                                wire:loading.attr="disabled"
                                style="
                        min-width:110px;
                        padding:12px 18px;
                        font-size:16px;
                        font-weight:700;
                    "
                            >
                                Fertig
                            </button>

                        @else

                            <button
                                type="button"
                                wire:click="
                        reopenGroupedItem(
                            {{ $job->id }},
                            {{ $item['id'] }}
                        )
                    "
                                wire:loading.attr="disabled"
                                title="Fertig-Markierung zurücknehmen"
                                style="
                        min-width:110px;
                        padding:12px 18px;
                        font-size:16px;
                        font-weight:700;
                    "
                            >
                                ✓ Fertig
                            </button>

                        @endif
                    </div>

                @endif


            @empty

                <div style=" padding:15px 0;border-top:1px solid #ddd;">
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
                    style="
                        width:100%;
                        margin-top:20px;
                        padding:15px;
                        font-size:18px;
                        font-weight:700;
                    "
                >
                    Gesamten Bon fertigstellen
                </button>

            @endif
        </article>

    @empty

        <div
            style="
                padding:40px 20px;
                text-align:center;
                border:1px dashed #aaa;
                border-radius:12px;
            "
        >
            Keine offenen Bons für diese Station.
        </div>

    @endforelse

</div>
