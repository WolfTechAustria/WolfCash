<div>

    <h1>Produktion</h1>

    <div
        style="
            display:grid;
            grid-template-columns:repeat(auto-fill,minmax(300px,1fr));
            gap:20px;
        "
    >

        @foreach($jobs as $job)

            <div
                style="
                    border:2px solid #333;
                    padding:20px;
                    border-radius:10px;
                "
            >

                <h2>
                    Tisch
                    {{ $job->order?->table?->number }}
                </h2>

                <p>
                    {{ $job->created_at->format('H:i') }}
                </p>

                <hr>

                @foreach($job->payload['items'] as $item)

                    <div
                        style="
                            font-size:22px;
                            margin-bottom:10px;
                        "
                    >

                        <strong>
                            {{ $item['quantity'] }}x
                        </strong>

                        {{ $item['name'] }}

                        @if($item['note'])

                            <br>

                            <small>
                                {{ $item['note'] }}
                            </small>

                        @endif

                    </div>

                @endforeach

                <hr>

                <button
                    wire:click="complete({{ $job->id }})"

                    style="
                        width:100%;
                        height:50px;
                        font-size:20px;
                    "
                >
                    Fertig
                </button>

            </div>

        @endforeach

    </div>

</div>
