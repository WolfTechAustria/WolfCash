<div class="mx-auto max-w-3xl px-4 pb-10 pt-4 sm:px-6">

    @if($paymentFinished)

        <div class="flex flex-col items-center gap-3 rounded-3xl border border-free/40 bg-free-soft px-6 py-12 text-center">

            <span class="text-5xl">✅</span>

            <h1 class="font-display text-2xl font-semibold">Zahlung erfolgreich</h1>

            <p class="text-dim">Tisch {{ $table->number }}</p>

            @if($lastPayment)
                <div class="mt-2">
                    <p class="text-sm text-dim">
                        Zahlungsart: {{ $lastPayment->payment_method }}
                    </p>
                    <p class="font-display text-3xl font-semibold tabular-nums text-free">
                        {{ number_format($lastPayment->amount, 2) }} €
                    </p>
                </div>
            @endif

            <div class="mt-6 flex w-full max-w-xs flex-col gap-2">
                <button
                    disabled
                    class="w-full cursor-not-allowed rounded-xl border border-line py-3 text-sm font-medium text-dim opacity-60"
                >
                    Beleg drucken
                </button>

                <button
                    wire:click="backToPos"
                    class="w-full rounded-xl bg-accent py-3 text-sm font-semibold text-accent-ink transition active:scale-[0.98]"
                >
                    Zurück zur Kasse
                </button>
            </div>

        </div>

    @else

        <div class="mb-4 flex items-center gap-3">
            <a
                href="{{ route('pos.index') }}"
                class="flex h-9 w-9 items-center justify-center rounded-full border border-line text-dim transition hover:border-accent hover:text-accent"
                aria-label="Zurück zur Kasse"
            >
                ←
            </a>
            <h1 class="font-display text-xl font-semibold tracking-tight">
                Abrechnung · Tisch {{ $table->number }}
            </h1>
        </div>

        @if(! $order)

            <div class="rounded-2xl border border-line bg-surface px-5 py-8 text-center">
                <p class="text-dim">Keine offene Bestellung vorhanden.</p>
                <a href="{{ route('pos.index') }}" class="mt-3 inline-block text-sm font-medium text-accent">
                    Zurück zur Kasse
                </a>
            </div>

        @else

            @error('payment')
            <div class="mb-4 rounded-lg bg-occupied-soft px-3 py-2 text-sm text-occupied">
                {{ $message }}
            </div>
            @enderror

            {{-- Summen-Kacheln --}}
            <div class="mb-5 grid grid-cols-2 gap-2.5 sm:grid-cols-4">
                <div class="rounded-xl border border-line bg-surface p-3">
                    <p class="text-xs font-medium uppercase tracking-wide text-dim">Gesamt</p>
                    <p class="font-display text-lg font-semibold tabular-nums">{{ number_format($this->totalAmount, 2) }} €</p>
                </div>
                <div class="rounded-xl border border-line bg-surface p-3">
                    <p class="text-xs font-medium uppercase tracking-wide text-dim">Bezahlt</p>
                    <p class="font-display text-lg font-semibold tabular-nums text-free">{{ number_format($this->paidAmount, 2) }} €</p>
                </div>
                <div class="rounded-xl border border-line bg-surface p-3">
                    <p class="text-xs font-medium uppercase tracking-wide text-dim">Offen</p>
                    <p class="font-display text-lg font-semibold tabular-nums text-occupied">{{ number_format($this->openAmount, 2) }} €</p>
                </div>
                <div class="rounded-xl border border-accent/50 bg-accent/10 p-3">
                    <p class="text-xs font-medium uppercase tracking-wide text-accent">Auswahl</p>
                    <p class="font-display text-lg font-semibold tabular-nums text-accent">{{ number_format($this->selectedTotal, 2) }} €</p>
                </div>
            </div>

            {{-- Offene Positionen --}}
            <h2 class="mb-2 text-xs font-semibold uppercase tracking-wide text-dim">Offene Positionen · antippen zum Auswählen</h2>

            <div class="mb-5 flex flex-col gap-2">
                @foreach($this->openItems as $item)

                    @php
                        $selectedQuantity = (int) (
                            $selectedForPayment[$item->id] ?? 0
                        );

                        $openQuantity = max(
                            0,
                            $item->open_quantity - $selectedQuantity
                        );
                    @endphp

                    @if($openQuantity > 0)
                        <button
                            wire:click="addToPayment({{ $item->id }})"
                            class="flex items-center justify-between rounded-xl border border-line bg-surface px-4 py-3 text-left transition active:scale-[0.99] active:border-accent"
                        >
                            <div class="min-w-0">
                                <p class="truncate font-medium">{{ $openQuantity }}× {{ $item->product->name }}</p>
                                @if($item->note)
                                    <p class="text-xs text-dim">Notiz: {{ $item->note }}</p>
                                @endif
                            </div>
                            <p class="shrink-0 pl-3 font-display font-semibold tabular-nums">
                                {{ number_format($item->price * $openQuantity, 2) }} €
                            </p>
                        </button>
                    @endif

                @endforeach
            </div>

            {{-- Zur Zahlung ausgewählt --}}
            @if(count($selectedForPayment) > 0)

                <h2 class="mb-2 text-xs font-semibold uppercase tracking-wide text-dim">Zu bezahlen · antippen zum Entfernen</h2>

                <div class="mb-5 flex flex-col gap-2">
                    @foreach($selectedForPayment as $itemId => $quantity)

                        @php
                            $item = $order->items->firstWhere(
                                'id',
                                (int) $itemId
                            );

                            $quantity = $item
                                ? min((int) $quantity, $item->open_quantity)
                                : 0;
                        @endphp

                        @if($item && $quantity > 0)
                            <button
                                wire:click="removeFromPayment({{ $item->id }})"
                                class="flex items-center justify-between rounded-xl border border-accent/50 bg-accent/10 px-4 py-3 text-left transition active:scale-[0.99]"
                            >
                                <div class="min-w-0">
                                    <p class="truncate font-medium">{{ $quantity }}× {{ $item->product->name }}</p>
                                    @if($item->note)
                                        <p class="text-xs text-dim">Notiz: {{ $item->note }}</p>
                                    @endif
                                </div>
                                <p class="shrink-0 pl-3 font-display font-semibold tabular-nums text-accent">
                                    {{ number_format($item->price * $quantity, 2) }} €
                                </p>
                            </button>
                        @endif

                    @endforeach
                </div>

            @endif

            {{-- Zahlungen-Verlauf --}}
            <h2 class="mb-2 text-xs font-semibold uppercase tracking-wide text-dim">Zahlungen</h2>

            @if($order->payments->isEmpty())

                <p class="mb-5 text-sm text-dim">Noch keine Zahlungen.</p>

            @else

                <div class="mb-5 flex flex-col gap-1.5">
                    @foreach($order->payments as $payment)
                        <div class="flex items-center justify-between rounded-lg bg-surface px-3 py-2 text-sm">
                            <span class="text-dim">{{ $payment->created_at->format('H:i') }} · {{ $payment->payment_method }}</span>
                            <span class="font-medium tabular-nums">{{ number_format($payment->amount, 2) }} €</span>
                        </div>
                    @endforeach
                </div>

            @endif

            {{-- Zahlungs-Aktionen --}}
            <div class="grid grid-cols-2 gap-2.5">
                <button
                    wire:click="paySelected('cash')"
                    class="rounded-xl bg-accent py-3 text-sm font-semibold text-accent-ink transition active:scale-[0.98]"
                >
                    Auswahl · Bar
                </button>
                <button
                    wire:click="paySelected('card')"
                    class="rounded-xl bg-accent py-3 text-sm font-semibold text-accent-ink transition active:scale-[0.98]"
                >
                    Auswahl · Karte
                </button>
                <button
                    wire:click="payOpen('cash')"
                    class="rounded-xl border border-line py-3 text-sm font-medium text-fg transition active:scale-[0.98]"
                >
                    Rest · Bar
                </button>
                <button
                    wire:click="payOpen('card')"
                    class="rounded-xl border border-line py-3 text-sm font-medium text-fg transition active:scale-[0.98]"
                >
                    Rest · Karte
                </button>
            </div>

        @endif

    @endif

</div>
