<div>

    @if(!$selectedTable)

        <h1>Tischauswahl</h1>

        <div style="display:flex;gap:20px;flex-wrap:wrap;">

            @foreach($tables as $table)

                <button
                    wire:click="selectTable({{ $table->id }})"
                    style="
                        width:120px;
                        height:120px;
                        font-size:30px;
                    "
                >
                    {{ $table->number }}
                </button>

            @endforeach

        </div>

    @else

        <button wire:click="backToTables">
            ← Zurück
        </button>

        <h1>
            Tisch {{ $table->number }}
        </h1>

        <hr>

        <div style="display:flex;height:70vh;">

            <div
                style="
                    width:35%;
                    border-right:1px solid #ccc;
                    padding:20px;
                "
            >
                <h2>Warenkorb</h2>

                <p>Noch keine Produkte</p>

            </div>

            <div
                style="
                    flex:1;
                    padding:20px;
                "
            >
                <h2>Produkte</h2>

                <p>Produktkategorien folgen im nächsten Kapitel</p>

            </div>

        </div>

    @endif

</div>
