<div>

    @if(!$selectedTable)

        <h1>Tischauswahl</h1>

        <div style="margin-bottom:20px;">

            <button wire:click="setTableSelectionMode('keypad')">
                Nummer eingeben
            </button>

            <button wire:click="setTableSelectionMode('list')">
                Liste anzeigen
            </button>

        </div>

        @if($tableSelectionMode === 'keypad')

            <div style="max-width:320px;">

                <div
                    style="
                border:1px solid #ccc;
                padding:20px;
                font-size:36px;
                text-align:center;
                margin-bottom:15px;
            "
                >
                    {{ $tableNumberInput ?: 'Tisch' }}
                </div>

                @error('tableNumberInput')
                <div style="color:red;margin-bottom:10px;">
                    {{ $message }}
                </div>
                @enderror

                <div
                    style="
                display:grid;
                grid-template-columns:repeat(3,1fr);
                gap:10px;
            "
                >

                    @foreach(['7','8','9','4','5','6','1','2','3'] as $number)

                        <button
                            wire:click="pressTableNumber('{{ $number }}')"
                            style="height:70px;font-size:28px;"
                        >
                            {{ $number }}
                        </button>

                    @endforeach

                    <button
                        wire:click="deleteLastTableNumber"
                        style="height:70px;font-size:24px;"
                    >
                        ⌫
                    </button>

                    <button
                        wire:click="pressTableNumber('0')"
                        style="height:70px;font-size:28px;"
                    >
                        0
                    </button>

                    <button
                        wire:click="confirmTableNumber"
                        style="height:70px;font-size:24px;"
                    >
                        OK
                    </button>

                </div>

                <br>

                <button wire:click="clearTableNumber">
                    Eingabe löschen
                </button>

            </div>

        @endif

        @if($tableSelectionMode === 'list')

            <div style="display:flex;gap:20px;flex-wrap:wrap;">

                @foreach($tables as $table)

                    <button
                        wire:click="selectTable({{ $table->id }})"
                        style="
                    width:140px;
                    height:140px;
                    font-size:20px;
                    border-radius:12px;
                    border:2px solid {{ $table->openOrder ? '#cc0000' : '#00aa00' }};
                    background:{{ $table->openOrder ? '#ffe5e5' : '#e8ffe8' }};
                "
                    >
                        <strong>
                            Tisch {{ $table->number }}
                        </strong>

                        <br><br>

                        @if($table->openOrder)

                            🔴 Belegt

                            <br>

                            {{ number_format($table->openOrder->total, 2) }} €

                        @else

                            🟢 Frei

                        @endif
                    </button>

                @endforeach

            </div>

        @endif

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

                <h2>Bereits boniert</h2>

                @forelse($orderItems as $item)

                    <div>

                        {{ $item['quantity'] }}x

                        {{ $item['name'] }}

                        @if(!empty($item['note']))
                            <br>
                            <small>
                                Notiz: {{ $item['note'] }}
                            </small>
                        @endif

                    </div>

                @empty

                    <p>Noch keine Bonierungen</p>

                @endforelse

                <hr>


                <h2>Warenkorb</h2>

                @forelse($cart as $item)

                    <div
                        style="
            border-bottom:1px solid #ccc;
            padding-bottom:10px;
            margin-bottom:10px;
        "
                    >

                        <div
                            style="
                display:flex;
                justify-content:space-between;
                align-items:center;
            "
                        >

                            <div>

                                <strong>
                                    {{ $item['name'] }}
                                </strong>

                                <input
                                    type="text"
                                    wire:model="cart.{{ $item['id'] }}.note"
                                    placeholder="Notiz, z.B. ohne Zwiebeln"
                                    style="width:100%; margin-top:5px;"
                                >

                                <br>

                                {{ number_format($item['price'], 2) }} €

                            </div>

                            <div
                                style="
                    display:flex;
                    align-items:center;
                    gap:10px;
                "
                            >

                                <button
                                    wire:click="removeProduct({{ $item['id'] }})"
                                    style="
                        width:40px;
                        height:40px;
                    "
                                >
                                    -
                                </button>

                                <strong>
                                    {{ $item['quantity'] }}
                                </strong>

                                <button wire:click="increaseProduct({{ $item['id'] }})" style="width:40px;height:40px;">+</button>

                            </div>

                        </div>

                    </div>

                @empty

                    <p>Keine Produkte</p>

                @endforelse

                <hr>

                <strong>
                    Gesamt:
                    {{ number_format($this->total, 2) }}
                </strong>

                <br><br>

                <button
                    wire:click="bonieren"
                    style="
                        padding:15px;
                        font-size:18px;
                    "
                >
                    Bonieren
                </button>

                <br><br>

                <a href="{{ route('pos.checkout', $selectedTable) }}">
                    Abrechnen
                </a>


            </div>

            <div
                style="
                    flex:1;
                    padding:20px;
                "
            >
                <h2>Produkte</h2>

                <div style="margin-bottom:20px;">

                    @foreach($groups as $group)

                        <button
                            wire:click="setGroup({{ $group->id }})"

                            style="
        padding:12px;
        margin-right:5px;
    "
                        >

                            {{ $group->name }}

                        </button>

                    @endforeach

                </div>

                <div style="margin-bottom:20px;">

                    @foreach($categories as $category)

                        <button
                            wire:click="setCategory({{ $category->id }})"

                            style="
        padding:10px;
        margin-right:5px;
    "
                        >

                            {{ $category->name }}

                        </button>

                    @endforeach

                </div>

                <div
                    style="
        display:grid;
        grid-template-columns:repeat(auto-fill,minmax(180px,1fr));
        gap:15px;
    "
                >

                    @foreach($products as $product)
                        @if($product->isSoldOut())

                            <button
                                disabled

                                style="
            height:120px;
            opacity:0.5;
        "
                            >

                                {{ $product->name }}

                                <br>

                                AUSVERKAUFT

                            </button>

                        @else

                        <button
                            wire:click="addProduct({{ $product->id }})"

                            style="
                                height:120px;
                                border:1px solid #ccc;
                                border-radius:10px;
                                padding:10px;

                                display:flex;
                                flex-direction:column;
                                justify-content:space-between;

                                text-align:left;
                            "
                        >
                            <div>

                                <strong>
                                    {{ $product->name }}
                                </strong>

                            </div>

                            <div>

                                {{ number_format($product->price, 2) }} €

                            </div>

                    </button>
                        @endif

                    @endforeach

                </div>

            </div>

        </div>

    @endif

</div>
