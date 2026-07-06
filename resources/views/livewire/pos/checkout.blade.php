<div>

    <h1>Abrechnung Tisch {{ $table->number }}</h1>

    @if(! $order)

        <p>Keine offene Bestellung vorhanden.</p>

        <a href="{{ route('pos.index') }}">
            Zurück zur POS
        </a>

    @else

        @error('payment')
        <div style="color:red;margin-bottom:10px;">
            {{ $message }}
        </div>
        @enderror

        <h2>Offene Positionen</h2>

        <table border="1" cellpadding="10" width="100%">
            <thead>
            <tr>
                <th>Produkt</th>
                <th>Offen</th>
                <th>Preis</th>
                <th>Summe</th>
            </tr>
            </thead>

            <tbody>
            @foreach($this->openItems as $item)

                @php
                    $selectedQuantity = $selectedForPayment[$item->id] ?? 0;
                    $openQuantity = $item->quantity - $selectedQuantity;
                @endphp

                @if($openQuantity > 0)
                    <tr
                        wire:click="addToPayment({{ $item->id }})"
                        style="cursor:pointer;"
                    >
                        <td>
                            {{ $item->product->name }}

                            @if($item->note)
                                <br>
                                <small>Notiz: {{ $item->note }}</small>
                            @endif
                        </td>

                        <td>{{ $openQuantity }}</td>

                        <td>{{ number_format($item->price, 2) }} €</td>

                        <td>{{ number_format($item->price * $openQuantity, 2) }} €</td>
                    </tr>
                @endif

            @endforeach
            </tbody>
        </table>

        <hr>

        <h2>Zu bezahlen</h2>

        <table border="1" cellpadding="10" width="100%">
            <thead>
            <tr>
                <th>Produkt</th>
                <th>Menge</th>
                <th>Preis</th>
                <th>Summe</th>
            </tr>
            </thead>

            <tbody>
            @foreach($selectedForPayment as $itemId => $quantity)

                @php
                    $item = $order->items->firstWhere('id', (int) $itemId);
                @endphp

                @if($item)
                    <tr
                        wire:click="removeFromPayment({{ $item->id }})"
                        style="cursor:pointer;"
                    >
                        <td>
                            {{ $item->product->name }}

                            @if($item->note)
                                <br>
                                <small>Notiz: {{ $item->note }}</small>
                            @endif
                        </td>

                        <td>{{ $quantity }}</td>

                        <td>{{ number_format($item->price, 2) }} €</td>

                        <td>{{ number_format($item->price * $quantity, 2) }} €</td>
                    </tr>
                @endif

            @endforeach
            </tbody>
        </table>

        <hr>

        <h2>
            Gesamtsumme:
            {{ number_format($order->total, 2) }} €
        </h2>

        <h3>
            Ausgewählt:
            {{ number_format($this->selectedTotal, 2) }} €
        </h3>

        <h3>
            Noch offen:
            {{ number_format(
                $this->openItems->sum(fn ($item) => $item->price * $item->quantity),
                2
            ) }} €
        </h3>

        <button wire:click="paySelected('cash')">
            Auswahl bar bezahlen
        </button>

        <button wire:click="paySelected('card')">
            Auswahl mit Karte bezahlen
        </button>

        <hr>

        <button wire:click="payAll('cash')">
            Alles bar bezahlen
        </button>

        <button wire:click="payAll('card')">
            Alles mit Karte bezahlen
        </button>

        <br><br>

        <a href="{{ route('pos.index') }}">
            Zurück zur POS
        </a>

    @endif

</div>
