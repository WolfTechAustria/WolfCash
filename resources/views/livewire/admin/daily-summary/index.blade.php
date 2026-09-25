<div>

    {{-- Kopf --}}
    <div class="mb-5 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">

        <div>
            <h1 class="font-display text-2xl font-semibold tracking-tight">
                Tagesübersicht
            </h1>

            <p class="mt-1 text-sm text-dim">
                Umsatz, Zahlungen, Stornos und offene Bestellungen.
            </p>
        </div>

        <div class="flex items-center gap-2">

            <button
                type="button"
                wire:click="previousDay"
                class="flex h-10 w-10 items-center justify-center rounded-xl border border-line text-dim transition hover:border-accent hover:text-accent"
                aria-label="Vorheriger Tag"
            >
                ←
            </button>

            <input
                type="date"
                wire:model.live="date"
                class="rounded-xl border border-line bg-surface px-3 py-2 text-sm focus:border-accent focus:outline-none"
            >

            <button
                type="button"
                wire:click="nextDay"
                class="flex h-10 w-10 items-center justify-center rounded-xl border border-line text-dim transition hover:border-accent hover:text-accent"
                aria-label="Nächster Tag"
            >
                →
            </button>

            <button
                type="button"
                wire:click="goToToday"
                class="rounded-xl border border-line px-3 py-2 text-sm font-medium text-dim transition hover:border-accent hover:text-accent"
            >
                Heute
            </button>

        </div>

    </div>

    <div class="mb-5 rounded-2xl border border-line bg-surface px-4 py-3">

        <p class="text-xs font-semibold uppercase tracking-wide text-dim">
            Ausgewählter Geschäftstag
        </p>

        <p class="mt-1 font-display text-xl font-semibold">
            {{ $selectedDate->translatedFormat('l, d. F Y') }}
        </p>

    </div>

    {{-- Tagesabschluss --}}
    <div class="mb-5">

        @if(session('success'))
            <div class="mb-3 rounded-xl border border-free/40 bg-free-soft px-4 py-3 text-sm font-medium text-free">
                {{ session('success') }}
            </div>
        @endif

        @error('dailyClosing')
        <div class="mb-3 rounded-xl border border-occupied/40 bg-occupied-soft px-4 py-3 text-sm text-occupied">
            {{ $message }}
        </div>
        @enderror

        @if($dailyClosing)

            @php
                $user = $dailyClosing->closedByUser;

                $userName = $user
                    ? trim(
                        ($user->first_name ?? '')
                        .' '.
                        ($user->last_name ?? '')
                    )
                    : '';

                if ($userName === '' && $user) {
                    $userName = $user->name
                        ?? 'Benutzer #'.$user->id;
                }
            @endphp

            <div class="rounded-2xl border border-free/40 bg-free-soft p-4">

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">

                    <div>

                        <div class="flex items-center gap-2">

                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-free/20 text-free">
                            ✓
                        </span>

                            <h2 class="font-display text-lg font-semibold text-free">
                                Geschäftstag abgeschlossen
                            </h2>

                        </div>

                        <p class="mt-2 text-sm text-dim">
                            Abschluss #{{ $dailyClosing->id }}
                            ·
                            {{ $dailyClosing->closed_at->format(
                                'd.m.Y H:i'
                            ) }}
                        </p>

                        <p class="mt-0.5 text-sm text-dim">
                            Durch:
                            {{ $userName !== ''
                                ? $userName
                                : 'Nicht zugeordnet' }}
                        </p>

                    </div>

                    <div class="text-left sm:text-right">

                        <p class="text-xs font-semibold uppercase tracking-wide text-dim">
                            Abgeschlossener Umsatz
                        </p>

                        <p class="mt-1 font-display text-2xl font-semibold tabular-nums text-free">
                            {{ number_format(
                                $dailyClosing->paid_amount,
                                2,
                                ',',
                                '.'
                            ) }} €
                        </p>

                    </div>

                </div>

                <p class="mt-3 rounded-xl bg-surface/50 px-3 py-2 text-xs text-dim">
                    Die gespeicherten Abschlusswerte werden nicht mehr
                    aus den aktuellen Bestellungsdaten neu berechnet.
                </p>

            </div>

        @else

            <div class="rounded-2xl border border-accent/40 bg-accent/10 p-4">

                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">

                    <div>

                        <h2 class="font-display text-lg font-semibold">
                            Geschäftstag abschließen
                        </h2>

                        <p class="mt-1 text-sm text-dim">
                            Der Abschluss speichert die aktuellen Tageswerte
                            dauerhaft als unveränderlichen Snapshot.
                        </p>

                        @if($summary['orders_open'] > 0)
                            <p class="mt-2 text-sm font-medium text-occupied">
                                Es sind noch
                                {{ $summary['orders_open'] }}
                                Bestellungen offen.
                            </p>
                        @endif

                    </div>

                    <button
                        type="button"
                        wire:click="closeDay"
                        wire:confirm="Geschäftstag {{ $selectedDate->format('d.m.Y') }} wirklich abschließen? Dieser Vorgang kann nicht rückgängig gemacht werden."
                        wire:loading.attr="disabled"
                        wire:target="closeDay"
                        @disabled(
                            $summary['orders_open'] > 0
                            || $selectedDate->isFuture()
                        )
                        class="shrink-0 rounded-xl bg-accent px-5 py-3 text-sm font-semibold text-accent-ink transition active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-40"
                    >
                    <span wire:loading.remove wire:target="closeDay">
                        Tagesabschluss durchführen
                    </span>

                        <span wire:loading wire:target="closeDay">
                        Wird abgeschlossen …
                    </span>
                    </button>

                </div>

            </div>

        @endif

    </div>

    {{-- Hauptkennzahlen --}}
    <div class="mb-5 grid grid-cols-2 gap-2.5 lg:grid-cols-4">

        <div class="rounded-xl border border-line bg-surface p-3">

            <p class="text-xs font-medium uppercase tracking-wide text-dim">
                Verrechenbarer Umsatz
            </p>

            <p class="mt-1 font-display text-xl font-semibold tabular-nums">
                {{ number_format(
                    $summary['payable_amount'],
                    2,
                    ',',
                    '.'
                ) }} €
            </p>

        </div>

        <div class="rounded-xl border border-free/40 bg-free-soft p-3">

            <p class="text-xs font-medium uppercase tracking-wide text-free">
                Bezahlt
            </p>

            <p class="mt-1 font-display text-xl font-semibold tabular-nums text-free">
                {{ number_format(
                    $summary['paid_amount'],
                    2,
                    ',',
                    '.'
                ) }} €
            </p>

        </div>

        <div class="rounded-xl border border-accent/40 bg-accent/10 p-3">

            <p class="text-xs font-medium uppercase tracking-wide text-accent">
                Noch offen
            </p>

            <p class="mt-1 font-display text-xl font-semibold tabular-nums text-accent">
                {{ number_format(
                    $summary['open_amount'],
                    2,
                    ',',
                    '.'
                ) }} €
            </p>

        </div>

        <div class="rounded-xl border border-occupied/40 bg-occupied-soft p-3">

            <p class="text-xs font-medium uppercase tracking-wide text-occupied">
                Storniert
            </p>

            <p class="mt-1 font-display text-xl font-semibold tabular-nums text-occupied">
                {{ number_format(
                    $summary['cancelled_amount'],
                    2,
                    ',',
                    '.'
                ) }} €
            </p>

        </div>

    </div>

    {{-- Zahlungsarten --}}
    <div class="mb-5 grid grid-cols-2 gap-2.5 md:grid-cols-5">

        <div class="rounded-xl border border-line bg-surface p-3">

            <p class="text-xs font-medium uppercase tracking-wide text-dim">
                Bar
            </p>

            <p class="mt-1 font-display text-lg font-semibold tabular-nums">
                {{ number_format(
                    $summary['cash_amount'],
                    2,
                    ',',
                    '.'
                ) }} €
            </p>

        </div>

        <div class="rounded-xl border border-line bg-surface p-3">

            <p class="text-xs font-medium uppercase tracking-wide text-dim">
                Karte
            </p>

            <p class="mt-1 font-display text-lg font-semibold tabular-nums">
                {{ number_format(
                    $summary['card_amount'],
                    2,
                    ',',
                    '.'
                ) }} €
            </p>

        </div>

        <div class="rounded-xl border border-dashed border-line bg-surface p-3">

            <p class="text-xs font-medium uppercase tracking-wide text-dim">
                Bons eingelöst
            </p>

            <p class="mt-1 font-display text-lg font-semibold tabular-nums">
                {{ number_format(
                    $summary['voucher_amount'],
                    2,
                    ',',
                    '.'
                ) }} €
            </p>

            <p class="mt-0.5 text-[11px] text-dim">
                nicht im Umsatz
            </p>

        </div>

        <div class="rounded-xl border border-line bg-surface p-3">

            <p class="text-xs font-medium uppercase tracking-wide text-dim">
                Zahlungen
            </p>

            <p class="mt-1 font-display text-lg font-semibold tabular-nums">
                {{ $summary['payments_count'] }}
            </p>

        </div>

        <div class="rounded-xl border border-line bg-surface p-3">

            <p class="text-xs font-medium uppercase tracking-wide text-dim">
                Ursprünglicher Wert
            </p>

            <p class="mt-1 font-display text-lg font-semibold tabular-nums">
                {{ number_format(
                    $summary['gross_amount'],
                    2,
                    ',',
                    '.'
                ) }} €
            </p>

        </div>

    </div>

    {{-- Bestell- und Stornozahlen --}}
    <div class="mb-6 grid grid-cols-2 gap-2.5 md:grid-cols-3 xl:grid-cols-6">

        <div class="rounded-xl border border-line bg-surface p-3">
            <p class="text-xs text-dim">Bestellungen</p>
            <p class="mt-1 font-display text-lg font-semibold">
                {{ $summary['orders_total'] }}
            </p>
        </div>

        <div class="rounded-xl border border-line bg-surface p-3">
            <p class="text-xs text-dim">Bezahlt</p>
            <p class="mt-1 font-display text-lg font-semibold text-free">
                {{ $summary['orders_paid'] }}
            </p>
        </div>

        <div class="rounded-xl border border-line bg-surface p-3">
            <p class="text-xs text-dim">Offen</p>
            <p class="mt-1 font-display text-lg font-semibold text-accent">
                {{ $summary['orders_open'] }}
            </p>
        </div>

        <div class="rounded-xl border border-line bg-surface p-3">
            <p class="text-xs text-dim">Vollstornos</p>
            <p class="mt-1 font-display text-lg font-semibold text-occupied">
                {{ $summary['orders_cancelled'] }}
            </p>
        </div>

        <div class="rounded-xl border border-line bg-surface p-3">
            <p class="text-xs text-dim">Stornovorgänge</p>
            <p class="mt-1 font-display text-lg font-semibold">
                {{ $summary['cancellations_count'] }}
            </p>
        </div>

        <div class="rounded-xl border border-line bg-surface p-3">
            <p class="text-xs text-dim">Stornierte Stück</p>
            <p class="mt-1 font-display text-lg font-semibold">
                {{ $summary['cancelled_quantity'] }}
            </p>
        </div>

    </div>

    <div class="grid gap-6 xl:grid-cols-2">

        {{-- Offene Bestellungen --}}
        <section>

            <div class="mb-2 flex items-center justify-between">

                <h2 class="font-display text-lg font-semibold">
                    Offene Bestellungen
                </h2>

                <span class="text-sm text-dim">
                    {{ $openOrders->count() }}
                </span>

            </div>

            <div class="overflow-hidden rounded-2xl border border-line bg-surface">

                @forelse($openOrders as $order)

                    @php
                        $payable = $order->items->sum(
                            fn ($item) =>
                                (float) $item->price
                                * max(
                                    0,
                                    (int) $item->quantity
                                    - (int) $item->cancelled_quantity
                                )
                        );

                        $paid = (float) $order
                            ->payments
                            ->sum('amount');

                        $open = max(
                            0,
                            $payable - $paid
                        );
                    @endphp

                    <div
                        wire:key="open-order-{{ $order->id }}"
                        class="flex items-center justify-between gap-3 border-b border-line px-4 py-3 last:border-b-0"
                    >

                        <div>

                            <a
                                href="{{ route(
                                    'admin.orders.show',
                                    $order
                                ) }}"
                                class="font-medium transition hover:text-accent"
                            >
                                Bestellung #{{ $order->id }}
                            </a>

                            <p class="mt-0.5 text-xs text-dim">
                                Tisch {{ $order->table?->number ?? '–' }}
                                ·
                                {{ $order->created_at->format('H:i') }}
                            </p>

                        </div>

                        <p class="font-display text-lg font-semibold tabular-nums text-accent">
                            {{ number_format(
                                $open,
                                2,
                                ',',
                                '.'
                            ) }} €
                        </p>

                    </div>

                @empty

                    <p class="px-4 py-8 text-center text-sm text-dim">
                        Keine offenen Bestellungen.
                    </p>

                @endforelse

            </div>

        </section>

        {{-- Zahlungsverlauf --}}
        <section>

            <div class="mb-2 flex items-center justify-between">

                <h2 class="font-display text-lg font-semibold">
                    Zahlungsverlauf
                </h2>

                <span class="text-sm text-dim">
                    {{ $payments->count() }}
                </span>

            </div>

            <div class="overflow-hidden rounded-2xl border border-line bg-surface">

                @forelse($payments as $payment)

                    <div
                        wire:key="daily-payment-{{ $payment->id }}"
                        class="flex items-center justify-between gap-3 border-b border-line px-4 py-3 last:border-b-0"
                    >

                        <div>

                            <p class="font-medium">
                                {{ \App\Models\Payment::methodLabel($payment->payment_method) }}
                            </p>

                            <p class="mt-0.5 text-xs text-dim">
                                {{ $payment->created_at->format('H:i') }}
                                ·
                                Tisch {{ $payment->order?->table?->number ?? '–' }}

                                @if($payment->order)
                                    ·
                                    <a
                                        href="{{ route(
                                            'admin.orders.show',
                                            $payment->order
                                        ) }}"
                                        class="text-accent"
                                    >
                                        #{{ $payment->order->id }}
                                    </a>
                                @endif
                            </p>

                        </div>

                        <p class="font-display text-lg font-semibold tabular-nums text-free">
                            {{ number_format(
                                $payment->amount,
                                2,
                                ',',
                                '.'
                            ) }} €
                        </p>

                    </div>

                @empty

                    <p class="px-4 py-8 text-center text-sm text-dim">
                        Keine Zahlungen an diesem Tag.
                    </p>

                @endforelse

            </div>

        </section>

    </div>

</div>
