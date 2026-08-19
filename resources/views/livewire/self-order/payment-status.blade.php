<div
    @if(
        $selfOrder->status
        !== \App\Models\SelfOrder::STATUS_SUBMITTED
    )
        wire:poll.2s="refreshStatus"
    @endif

    class="mx-auto max-w-lg px-4 py-16 text-center"
>

    @if(
        $selfOrder->status
        === \App\Models\SelfOrder::STATUS_SUBMITTED
    )

        <div
            class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-full bg-accent/10 text-3xl text-accent"
        >
            ✓
        </div>

        <h1
            class="font-display text-2xl font-semibold"
        >
            Bestellung übermittelt
        </h1>

        <p
            class="mt-3 text-sm leading-relaxed text-dim"
        >
            Deine Zahlung war erfolgreich.
        </p>

        <p
            class="mt-2 text-sm leading-relaxed text-dim"
        >
            Deine Bestellung wurde an Küche
            oder Bar übermittelt.
        </p>

        <div
            class="mt-6 rounded-2xl border border-line bg-surface p-4"
        >
            <div
                class="text-xs uppercase tracking-wide text-dim"
            >
                Tisch
            </div>

            <div
                class="mt-1 font-display text-xl font-semibold"
            >
                {{ $selfOrder->table->number }}
            </div>

            <a
                href="{{ route('self-order.continue', absolute: false) }}"

                class="mt-6 block w-full rounded-xl bg-accent px-4 py-3.5 text-center font-semibold text-accent-ink transition active:scale-[0.98]"
            >
                Weitere Bestellung aufgeben
            </a>
        </div>


    @elseif(
        $selfOrder->status
        === \App\Models\SelfOrder::STATUS_PAID
    )

        <div
            class="mx-auto mb-5 h-10 w-10 animate-spin rounded-full border-4 border-line border-t-accent"
        ></div>

        <h1
            class="font-display text-2xl font-semibold"
        >
            Zahlung erfolgreich
        </h1>

        <p
            class="mt-3 text-sm text-dim"
        >
            Deine Bestellung wird gerade
            an Küche oder Bar übermittelt.
        </p>


    @elseif(
        in_array(
            $selfOrder->status,
            [
                \App\Models\SelfOrder::STATUS_AWAITING_PAYMENT,
                \App\Models\SelfOrder::STATUS_PAYMENT_PROCESSING,
            ],
            true
        )
    )

        <div
            class="mx-auto mb-5 h-10 w-10 animate-spin rounded-full border-4 border-line border-t-accent"
        ></div>

        <h1
            class="font-display text-2xl font-semibold"
        >
            Zahlung wird geprüft
        </h1>

        <p
            class="mt-3 text-sm leading-relaxed text-dim"
        >
            Bitte diese Seite nicht schließen.
        </p>

        <p
            class="mt-2 text-sm leading-relaxed text-dim"
        >
            Deine Bestellung wird erst nach
            bestätigter Zahlung übermittelt.
        </p>


    @elseif(
        $selfOrder->status
        === \App\Models\SelfOrder::STATUS_CANCELLED
    )

        <h1
            class="font-display text-2xl font-semibold"
        >
            Bestellung abgebrochen
        </h1>

        <p
            class="mt-3 text-sm text-dim"
        >
            Es wurde keine Bestellung an
            Küche oder Bar übermittelt.
        </p>


    @elseif(
        $selfOrder->status
        === \App\Models\SelfOrder::STATUS_EXPIRED
    )

        <h1
            class="font-display text-2xl font-semibold"
        >
            Bestellung abgelaufen
        </h1>

        <p
            class="mt-3 text-sm text-dim"
        >
            Bitte scanne den QR-Code am Tisch
            erneut und starte eine neue Bestellung.
        </p>


    @else

        <h1
            class="font-display text-2xl font-semibold"
        >
            Bestellung wird verarbeitet
        </h1>

        <p
            class="mt-3 text-sm text-dim"
        >
            Bitte einen Moment warten.
        </p>

    @endif

</div>
