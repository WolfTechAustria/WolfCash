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

                @forelse($cart as $item)

                    <div
                        style="
            display:flex;
            justify-content:space-between;
            margin-bottom:10px;
        "
                    >

                        <div>
                            {{ $item['quantity'] }}x
                            {{ $item['name'] }}
                        </div>

                        <div>

                            <button
                                wire:click="removeProduct({{ $item['id'] }})"
                            >
                                -
                            </button>

                        </div>

                    </div>

                @empty

                    <p>Keine Produkte</p>

                @endforelse

                <hr>

                <strong>
                    Gesamt:
                    {{ number_format($this->total, 2) }} €
                </strong>


            </div>

            <div
                style="
                    flex:1;
                    padding:20px;
                "
            >
                <h2>Produkte</h2>

                <div
                    style="
                    display:flex;
                    gap:15px;
                    flex-wrap:wrap;
                    "
                >

                    @foreach($products as $product)
                    <button
                        wire:click="addProduct({{ $product->id }})"

                        style="
                            width:150px;
                            height:100px;
                            "
                    >
                        <strong>
                            {{ $product->name }}
                        </strong>

                        <br>

                        {{ number_format($product->price, 2) }} €

                    </button>

                    @endforeach

                </div>

            </div>

        </div>

    @endif

</div>
