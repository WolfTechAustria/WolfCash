<div>

    @if($paymentFinished)

        <div style="text-align:center;padding:40px;">

            <h1>✅ Zahlung erfolgreich</h1>

            <h2>
                Tisch {{ $table->number }}
            </h2>

            @if($lastPayment)

                <p>
                    Zahlungsart:
                    {{ $lastPayment->payment_method }}
                </p>

                <h2>
                    {{ number_format($lastPayment->amount, 2) }} €
                </h2>

            @endif

            <br>

            <button disabled>
                Beleg drucken
            </button>

            <br><br>

            <button wire:click="backToPos">
                Zurück zur POS
            </button>

        </div>

    @else

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

        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:15px;">

            <div style="border:1px solid #ccc;padding:15px;">
                <strong>Gesamtbetrag</strong>
                <br>
                {{ number_format($this->totalAmount, 2) }} €
            </div>

            <div style="border:1px solid #ccc;padding:15px;">
                <strong>Bereits bezahlt</strong>
                <br>
                {{ number_format($this->paidAmount, 2) }} €
            </div>

            <div style="border:1px solid #ccc;padding:15px;">
                <strong>Noch offen</strong>
                <br>
                {{ number_format($this->openAmount, 2) }} €
            </div>

            <div style="border:1px solid #ccc;padding:15px;">
                <strong>Aktuelle Auswahl</strong>
                <br>
                {{ number_format($this->selectedTotal, 2) }} €
            </div>

        </div>

        <hr>

        <h2>Zahlungen</h2>

        @if($order->payments->isEmpty())

            <p>Noch keine Zahlungen.</p>

        @else

            <table border="1" cellpadding="10" width="100%">
                <thead>
                <tr>
                    <th>Zeit</th>
                    <th>Zahlungsart</th>
                    <th>Betrag</th>
                </tr>
                </thead>

                <tbody>
                @foreach($order->payments as $payment)

                    <tr>
                        <td>
                            {{ $payment->created_at->format('H:i') }}
                        </td>

                        <td>
                            {{ $payment->payment_method }}
                        </td>

                        <td>
                            {{ number_format($payment->amount, 2) }} €
                        </td>
                    </tr>

                @endforeach
                </tbody>
            </table>

        @endif

        <button wire:click="paySelected('cash')">
            Auswahl bar bezahlen
        </button>

        <button wire:click="paySelected('card')">
            Auswahl mit Karte bezahlen
        </button>

        <hr>

        <button wire:click="payOpen('cash')">
            Rest bar bezahlen
        </button>

        <button wire:click="payOpen('card')">
            Rest mit Karte bezahlen
        </button>

        <br><br>

        <a href="{{ route('pos.index') }}">
            Zurück zur POS
        </a>

    @endif

    @endif
</div>

