<div class="mx-auto max-w-6xl">

    @php
        $summary = $snapshot['summary'] ?? [];
        $orders = $snapshot['orders'] ?? [];
        $payments = $snapshot['payments'] ?? [];
        $cancellations = $snapshot['cancellations'] ?? [];

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

    {{-- Kopf --}}
    <div class="mb-5 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">

        <div class="flex items-center gap-3">

            <a
                href="{{ route('admin.daily-closings') }}"
                class="flex h-10 w-10 items-center justify-center rounded-full border border-line text-dim transition hover:border-accent hover:text-accent"
            >
                ←
            </a>

            <div>
                <h1 class="font-display text-2xl font-semibold tracking-tight">
                    Tagesabschluss
                    {{ $dailyClosing->business_date->format('d.m.Y') }}
                </h1>

                <p class="mt-0.5 text-sm text-dim">
                    Abschluss #{{ $dailyClosing->id }}
                    ·
                    {{ $dailyClosing->closed_at->format('d.m.Y H:i') }}
                    ·
                    {{ $userName !== ''
                        ? $userName
                        : 'Nicht zugeordnet' }}
                </p>
            </div>

        </div>

        <span class="self-start rounded-full border border-free/30 bg-free/15 px-3 py-1.5 text-sm font-medium text-free">
            Unveränderlicher Snapshot
        </span>

    </div>

    {{-- Summen --}}
    <div class="mb-6 grid grid-cols-2 gap-2.5 lg:grid-cols-5">

        <div class="rounded-xl border border-line bg-surface p-3">
            <p class="text-xs uppercase tracking-wide text-dim">
                Ursprünglich
            </p>

            <p class="mt-1 font-display text-xl font-semibold tabular-nums">
                {{ number_format(
                    $summary['gross_amount']
                        ?? $dailyClosing->gross_amount,
                    2,
                    ',',
                    '.'
                ) }} €
            </p>
        </div>

        <div class="rounded-xl border border-occupied/40 bg-occupied-soft p-3">
            <p class="text-xs uppercase tracking-wide text-occupied">
                Storniert
            </p>

            <p class="mt-1 font-display text-xl font-semibold tabular-nums text-occupied">
                {{ number_format(
                    $summary['cancelled_amount']
                        ?? $dailyClosing->cancelled_amount,
                    2,
                    ',',
                    '.'
                ) }} €
            </p>
        </div>

        <div class="rounded-xl border border-line bg-surface p-3">
            <p class="text-xs uppercase tracking-wide text-dim">
                Verrechenbar
            </p>

            <p class="mt-1 font-display text-xl font-semibold tabular-nums">
                {{ number_format(
                    $summary['payable_amount']
                        ?? $dailyClosing->payable_amount,
                    2,
                    ',',
                    '.'
                ) }} €
            </p>
        </div>

        <div class="rounded-xl border border-free/40 bg-free-soft p-3">
            <p class="text-xs uppercase tracking-wide text-free">
                Bezahlt
            </p>

            <p class="mt-1 font-display text-xl font-semibold tabular-nums text-free">
                {{ number_format(
                    $summary['paid_amount']
                        ?? $dailyClosing->paid_amount,
                    2,
                    ',',
                    '.'
                ) }} €
            </p>
        </div>

        <div class="col-span-2 rounded-xl border border-accent/40 bg-accent/10 p-3 lg:col-span-1">
            <p class="text-xs uppercase tracking-wide text-accent">
                Zahlungen
            </p>

            <p class="mt-1 font-display text-xl font-semibold tabular-nums text-accent">
                {{ $summary['payments_count']
                    ?? $dailyClosing->payments_count }}
            </p>
        </div>

    </div>

    <div class="mb-6 grid grid-cols-2 gap-2.5 md:grid-cols-4">

        <div class="rounded-xl border border-line bg-surface p-3">
            <p class="text-xs text-dim">Bar</p>

            <p class="mt-1 font-display text-lg font-semibold tabular-nums">
                {{ number_format(
                    $summary['cash_amount']
                        ?? $dailyClosing->cash_amount,
                    2,
                    ',',
                    '.'
                ) }} €
            </p>
        </div>

        <div class="rounded-xl border border-line bg-surface p-3">
            <p class="text-xs text-dim">Karte</p>

            <p class="mt-1 font-display text-lg font-semibold tabular-nums">
                {{ number_format(
                    $summary['card_amount']
                        ?? $dailyClosing->card_amount,
                    2,
                    ',',
                    '.'
                ) }} €
            </p>
        </div>

        @if(($summary['voucher_amount'] ?? 0) > 0)
            <div class="rounded-xl border border-dashed border-line bg-surface p-3">
                <p class="text-xs text-dim">Bons eingelöst (nicht im Umsatz)</p>

                <p class="mt-1 font-display text-lg font-semibold tabular-nums">
                    {{ number_format(
                        $summary['voucher_amount'],
                        2,
                        ',',
                        '.'
                    ) }} €
                </p>
            </div>
        @endif

        <div class="rounded-xl border border-line bg-surface p-3">
            <p class="text-xs text-dim">Bestellungen</p>

            <p class="mt-1 font-display text-lg font-semibold">
                {{ $summary['orders_total']
                    ?? $dailyClosing->orders_total }}
            </p>
        </div>

        <div class="rounded-xl border border-line bg-surface p-3">
            <p class="text-xs text-dim">Stornovorgänge</p>

            <p class="mt-1 font-display text-lg font-semibold text-occupied">
                {{ $summary['cancellations_count']
                    ?? $dailyClosing->cancellations_count }}
            </p>
        </div>

    </div>

    {{-- Snapshot-Bestellungen --}}
    <section class="mb-6">

        <div class="mb-2 flex items-center justify-between">
            <h2 class="font-display text-lg font-semibold">
                Bestellungen
            </h2>

            <span class="text-sm text-dim">
                {{ count($orders) }}
            </span>
        </div>

        <div class="space-y-3">

            @forelse($orders as $order)

                <article class="rounded-2xl border border-line bg-surface p-4">

                    <div class="flex items-start justify-between gap-3">

                        <div>
                            <p class="font-medium">
                                Bestellung #{{ $order['id'] ?? '–' }}
                            </p>

                            <p class="mt-0.5 text-sm text-dim">
                                Tisch {{ $order['table_number'] ?? '–' }}
                                ·
                                {{ isset($order['created_at'])
                                    ? \Carbon\Carbon::parse(
                                        $order['created_at']
                                    )->format('H:i')
                                    : '–' }}
                            </p>
                        </div>

                        <span class="rounded-full bg-surface-2 px-2.5 py-1 text-xs font-medium text-dim">
                            {{ $order['status'] ?? '–' }}
                        </span>

                    </div>

                    <div class="mt-3 space-y-2">

                        @foreach($order['items'] ?? [] as $item)

                            @php
                                $payableQuantity = max(
                                    0,
                                    (int) ($item['quantity'] ?? 0)
                                    - (int) (
                                        $item['cancelled_quantity']
                                        ?? 0
                                    )
                                );
                            @endphp

                            <div class="flex items-start justify-between gap-3 rounded-xl bg-surface-2 px-3 py-2 text-sm">

                                <div>
                                    <p>
                                        {{ $payableQuantity }}×
                                        {{ $item['product_name']
                                            ?? 'Unbekanntes Produkt' }}
                                    </p>

                                    @if(($item['cancelled_quantity'] ?? 0) > 0)
                                        <p class="mt-0.5 text-xs text-occupied">
                                            {{ $item['cancelled_quantity'] }}
                                            storniert
                                        </p>
                                    @endif
                                </div>

                                <p class="tabular-nums">
                                    {{ number_format(
                                        $payableQuantity
                                        * (float) (
                                            $item['price']
                                            ?? 0
                                        ),
                                        2,
                                        ',',
                                        '.'
                                    ) }} €
                                </p>

                            </div>

                        @endforeach

                    </div>

                </article>

            @empty

                <div class="rounded-2xl border border-line bg-surface px-4 py-8 text-center text-dim">
                    Im Snapshot sind keine Bestellungen enthalten.
                </div>

            @endforelse

        </div>

    </section>

    <div class="grid gap-6 xl:grid-cols-2">

        {{-- Snapshot-Zahlungen --}}
        <section>

            <h2 class="mb-2 font-display text-lg font-semibold">
                Zahlungen
            </h2>

            <div class="overflow-hidden rounded-2xl border border-line bg-surface">

                @forelse($payments as $payment)

                    <div class="flex items-center justify-between gap-3 border-b border-line px-4 py-3 last:border-b-0">

                        <div>
                            <p class="font-medium">
                                {{ \App\Models\Payment::methodLabel($payment['payment_method'] ?? null) }}
                            </p>

                            <p class="mt-0.5 text-xs text-dim">
                                Tisch {{ $payment['table_number'] ?? '–' }}
                                · Bestellung
                                #{{ $payment['order_id'] ?? '–' }}
                            </p>
                        </div>

                        <p class="font-display text-lg font-semibold tabular-nums text-free">
                            {{ number_format(
                                $payment['amount'] ?? 0,
                                2,
                                ',',
                                '.'
                            ) }} €
                        </p>

                    </div>

                @empty

                    <p class="px-4 py-8 text-center text-sm text-dim">
                        Keine Zahlungen im Snapshot.
                    </p>

                @endforelse

            </div>

        </section>

        {{-- Snapshot-Stornos --}}
        <section>

            <h2 class="mb-2 font-display text-lg font-semibold">
                Stornos
            </h2>

            <div class="overflow-hidden rounded-2xl border border-line bg-surface">

                @forelse($cancellations as $cancellation)

                    <div class="border-b border-line px-4 py-3 last:border-b-0">

                        <div class="flex items-start justify-between gap-3">

                            <div>
                                <p class="font-medium">
                                    {{ $cancellation['quantity'] ?? 0 }}×
                                    {{ $cancellation['product_name']
                                        ?? 'Unbekanntes Produkt' }}
                                </p>

                                <p class="mt-0.5 text-xs text-dim">
                                    Tisch
                                    {{ $cancellation['table_number'] ?? '–' }}
                                    · Bestellung
                                    #{{ $cancellation['order_id'] ?? '–' }}
                                </p>
                            </div>

                            <p class="font-medium tabular-nums text-occupied">
                                {{ number_format(
                                    (float) (
                                        $cancellation['price']
                                        ?? 0
                                    )
                                    * (int) (
                                        $cancellation['quantity']
                                        ?? 0
                                    ),
                                    2,
                                    ',',
                                    '.'
                                ) }} €
                            </p>

                        </div>

                        <p class="mt-2 rounded-lg bg-occupied-soft px-2.5 py-2 text-sm text-occupied">
                            {{ $cancellation['reason']
                                ?? 'Kein Grund gespeichert' }}
                        </p>

                    </div>

                @empty

                    <p class="px-4 py-8 text-center text-sm text-dim">
                        Keine Stornos im Snapshot.
                    </p>

                @endforelse

            </div>

        </section>

    </div>

</div>
