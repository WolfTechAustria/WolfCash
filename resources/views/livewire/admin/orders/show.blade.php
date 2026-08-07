<div class="mx-auto max-w-6xl">

    {{-- Kopf --}}
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">

        <div class="flex items-center gap-3">

            <a
                href="{{ route('admin.orders') }}"
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-line text-dim transition hover:border-accent hover:text-accent"
                aria-label="Zurück zu Bestellungen"
            >
                ←
            </a>

            <div>
                <h1 class="font-display text-2xl font-semibold tracking-tight">
                    Bestellung #{{ $order->id }}
                </h1>

                <p class="mt-0.5 text-sm text-dim">
                    Tisch {{ $order->table?->number ?? '–' }}
                    ·
                    {{ $order->created_at->format('d.m.Y H:i') }}
                </p>
            </div>

        </div>

        @php
            $statusBadge = match($order->status) {
                'paid' => [
                    'bg-free/15 text-free border-free/30',
                    'Bezahlt',
                ],
                'cancelled' => [
                    'bg-occupied/15 text-occupied border-occupied/30',
                    'Storniert',
                ],
                'sent' => [
                    'bg-accent/15 text-accent border-accent/30',
                    'Boniert',
                ],
                default => [
                    'bg-surface text-dim border-line',
                    'Offen',
                ],
            };
        @endphp

        <span class="self-start rounded-full border px-3 py-1.5 text-sm font-medium {{ $statusBadge[0] }}">
            {{ $statusBadge[1] }}
        </span>

    </div>

    {{-- Summen --}}
    <div class="mb-6 grid grid-cols-2 gap-2.5 lg:grid-cols-5">

        <div class="rounded-xl border border-line bg-surface p-3">
            <p class="text-xs font-medium uppercase tracking-wide text-dim">
                Ursprünglich
            </p>

            <p class="mt-1 font-display text-xl font-semibold tabular-nums">
                {{ number_format(
                    $this->grossAmount,
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
                    $this->cancelledAmount,
                    2,
                    ',',
                    '.'
                ) }} €
            </p>
        </div>

        <div class="rounded-xl border border-line bg-surface p-3">
            <p class="text-xs font-medium uppercase tracking-wide text-dim">
                Verrechenbar
            </p>

            <p class="mt-1 font-display text-xl font-semibold tabular-nums">
                {{ number_format(
                    $this->payableAmount,
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
                    $this->paidAmount,
                    2,
                    ',',
                    '.'
                ) }} €
            </p>
        </div>

        <div class="col-span-2 rounded-xl border border-accent/40 bg-accent/10 p-3 lg:col-span-1">
            <p class="text-xs font-medium uppercase tracking-wide text-accent">
                Offen
            </p>

            <p class="mt-1 font-display text-xl font-semibold tabular-nums text-accent">
                {{ number_format(
                    $this->openAmount,
                    2,
                    ',',
                    '.'
                ) }} €
            </p>
        </div>

    </div>

    {{-- Positionen --}}
    <section class="mb-6">

        <div class="mb-2 flex items-center justify-between">

            <h2 class="font-display text-lg font-semibold">
                Positionen
            </h2>

            <span class="text-sm text-dim">
                {{ $order->items->count() }} Datensätze
            </span>

        </div>

        <div class="space-y-3">

            @forelse($order->items as $item)

                @php
                    $grossLine =
                        (float) $item->price
                        * (int) $item->quantity;

                    $cancelledLine =
                        (float) $item->price
                        * (int) $item->cancelled_quantity;

                    $payableLine =
                        (float) $item->price
                        * (int) $item->open_quantity;
                @endphp

                <article
                    wire:key="order-item-{{ $item->id }}"
                    class="overflow-hidden rounded-2xl border border-line bg-surface"
                >
                    <div class="p-4">

                        <div class="flex items-start justify-between gap-4">

                            <div class="min-w-0">

                                <div class="flex flex-wrap items-center gap-2">

                                    <h3 class="font-medium">
                                        {{ $item->product?->name ?? 'Unbekanntes Produkt' }}
                                    </h3>

                                    @if($item->cancelled_quantity > 0)
                                        <span class="rounded-full bg-occupied/15 px-2 py-0.5 text-xs font-medium text-occupied">
                                            {{ $item->cancelled_quantity }}
                                            storniert
                                        </span>
                                    @endif

                                    @if($item->paid_at)
                                        <span class="rounded-full bg-free/15 px-2 py-0.5 text-xs font-medium text-free">
                                            Bezahlt
                                        </span>
                                    @endif

                                </div>

                                @if($item->note)
                                    <p class="mt-1 text-sm text-dim">
                                        Notiz: {{ $item->note }}
                                    </p>
                                @endif

                                <p class="mt-1 text-xs text-dim">
                                    Position #{{ $item->id }}
                                </p>

                            </div>

                            <div class="shrink-0 text-right">

                                <p class="font-display text-lg font-semibold tabular-nums">
                                    {{ number_format(
                                        $payableLine,
                                        2,
                                        ',',
                                        '.'
                                    ) }} €
                                </p>

                                <p class="text-xs text-dim">
                                    {{ number_format(
                                        $item->price,
                                        2,
                                        ',',
                                        '.'
                                    ) }} € / Stück
                                </p>

                            </div>

                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-2 text-sm sm:grid-cols-4">

                            <div class="rounded-xl bg-surface-2 p-3">
                                <p class="text-xs text-dim">
                                    Boniert
                                </p>

                                <p class="mt-1 font-medium tabular-nums">
                                    {{ $item->quantity }}
                                </p>
                            </div>

                            <div class="rounded-xl bg-surface-2 p-3">
                                <p class="text-xs text-dim">
                                    Storniert
                                </p>

                                <p class="mt-1 font-medium tabular-nums text-occupied">
                                    {{ $item->cancelled_quantity }}
                                </p>
                            </div>

                            <div class="rounded-xl bg-surface-2 p-3">
                                <p class="text-xs text-dim">
                                    Verrechenbar
                                </p>

                                <p class="mt-1 font-medium tabular-nums">
                                    {{ $item->open_quantity }}
                                </p>
                            </div>

                            <div class="rounded-xl bg-surface-2 p-3">
                                <p class="text-xs text-dim">
                                    Ursprünglicher Betrag
                                </p>

                                <p class="mt-1 font-medium tabular-nums">
                                    {{ number_format(
                                        $grossLine,
                                        2,
                                        ',',
                                        '.'
                                    ) }} €
                                </p>
                            </div>

                        </div>

                        @if($cancelledLine > 0)
                            <div class="mt-3 flex items-center justify-between rounded-xl bg-occupied-soft px-3 py-2 text-sm">

                                <span class="text-occupied">
                                    Stornierter Betrag
                                </span>

                                <span class="font-medium tabular-nums text-occupied">
                                    {{ number_format(
                                        $cancelledLine,
                                        2,
                                        ',',
                                        '.'
                                    ) }} €
                                </span>

                            </div>
                        @endif

                    </div>

                    {{-- Stornohistorie der Position --}}
                    @if($item->cancellations->isNotEmpty())

                        <div class="border-t border-line bg-surface-2/50 px-4 py-3">

                            <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-dim">
                                Stornohistorie
                            </h4>

                            <div class="space-y-2">

                                @foreach($item->cancellations->sortByDesc('cancelled_at') as $cancellation)

                                    @php
                                        $user = $cancellation->cancelledByUser;

                                        $userName = $user
                                            ? trim(
                                                ($user->first_name ?? '')
                                                .' '.
                                                ($user->last_name ?? '')
                                            )
                                            : '';

                                        if ($userName === '' && $user) {
                                            $userName = $user->name
                                                ?? 'Unbekannter Benutzer';
                                        }
                                    @endphp

                                    <div
                                        wire:key="cancellation-{{ $cancellation->id }}"
                                        class="rounded-xl border border-occupied/20 bg-occupied-soft px-3 py-2 text-sm"
                                    >
                                        <div class="flex items-center justify-between gap-3">

                                            <span class="font-medium text-occupied">
                                                {{ $cancellation->quantity }} Stück
                                            </span>

                                            <span class="text-xs tabular-nums text-dim">
                                                {{ $cancellation->cancelled_at?->format('d.m.Y H:i') }}
                                            </span>

                                        </div>

                                        <p class="mt-1">
                                            {{ $cancellation->reason }}
                                        </p>

                                        <p class="mt-1 text-xs text-dim">
                                            Durch:
                                            {{ $userName !== ''
                                                ? $userName
                                                : 'Nicht zugeordnet' }}
                                        </p>

                                    </div>

                                @endforeach

                            </div>

                        </div>

                    @endif

                </article>

            @empty

                <div class="rounded-2xl border border-line bg-surface px-5 py-8 text-center text-dim">
                    Diese Bestellung enthält keine Positionen.
                </div>

            @endforelse

        </div>

    </section>

    <div class="grid gap-6 lg:grid-cols-2">

        {{-- Zahlungen --}}
        <section>

            <div class="mb-2 flex items-center justify-between gap-3">

                <h2 class="font-display text-lg font-semibold">
                    Zahlungen
                </h2>



                <span class="text-sm text-dim">
            {{ $order->payments->count() }}
        </span>

            </div>

            @if(! $receiptReprintingEnabled)
                <p class="mt-2 text-xs text-occupied">
                    Der manuelle Zahlungsbelegdruck ist global deaktiviert.
                </p>
            @endif

            @if(session('receiptReprintSuccess'))
                <div class="mb-3 rounded-xl border border-free/40 bg-free-soft px-4 py-3 text-sm text-free">
                    {{ session('receiptReprintSuccess') }}
                </div>
            @endif

            @error('receiptReprint')
            <div class="mb-3 rounded-xl border border-occupied/40 bg-occupied-soft px-4 py-3 text-sm text-occupied">
                {{ $message }}
            </div>
            @enderror

            <div class="overflow-hidden rounded-2xl border border-line bg-surface">

                @forelse($order->payments->sortByDesc('created_at') as $payment)

                    @php
                        $receiptJob = $payment->receiptPrintJob;

                        $originalOutput = $receiptJob
                            ?->outputs
                            ?->sortBy('created_at')
                            ?->first();

                        $reprintCount = $receiptJob
                            ?->outputs
                            ?->filter(
                                fn ($output) =>
                                    (bool) data_get(
                                        $output->payload,
                                        'reprint',
                                        false
                                    )
                            )
                            ?->count() ?? 0;

                        $receiptStatus = match (true) {
    ! $receiptJob => [
        'text-occupied',
        'Kein Beleg-Snapshot',
    ],

    ! $originalOutput => [
        'text-dim',
        'Noch nicht gedruckt',
    ],

    $originalOutput->status === 'printed' => [
        'text-free',
        'Gedruckt',
    ],

    $originalOutput->status === 'failed' => [
        'text-occupied',
        'Fehlgeschlagen',
    ],

    $originalOutput->status === 'printing' => [
        'text-accent',
        'Wird gedruckt',
    ],

    default => [
        'text-dim',
        'Ausstehend',
    ],
};
                    @endphp

                    <div
                        wire:key="payment-{{ $payment->id }}"
                        class="border-b border-line px-4 py-3 last:border-b-0"
                    >

                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">

                            <div>

                                <div class="flex flex-wrap items-center gap-2">

                                    <p class="font-medium">
                                        {{ match($payment->payment_method) {
                                            'cash' => 'Barzahlung',
                                            'card' => 'Kartenzahlung',
                                            'voucher' => 'Gutschein',
                                            'invoice' => 'Rechnung',
                                            'house' => 'Auf Haus',
                                            default => $payment->payment_method,
                                        } }}
                                    </p>

                                    <span class="text-xs font-medium {{ $receiptStatus[0] }}">
                                {{ $receiptStatus[1] }}
                            </span>

                                </div>

                                <p class="mt-0.5 text-xs text-dim">
                                    Zahlung #{{ $payment->id }}
                                    ·
                                    {{ $payment->created_at->format('d.m.Y H:i') }}
                                </p>

                                @if($receiptJob)
                                    <p class="mt-0.5 text-xs text-dim">
                                        Belegjob #{{ $receiptJob->id }}

                                        @if($reprintCount > 0)
                                            ·
                                            {{ $reprintCount }}
                                            {{ $reprintCount === 1
                                                ? 'Nachdruck'
                                                : 'Nachdrucke' }}
                                        @endif
                                    </p>
                                @endif

                            </div>

                            <div class="flex items-center justify-between gap-3 sm:justify-end">

                                <p class="font-display text-lg font-semibold tabular-nums text-free">
                                    {{ number_format(
                                        $payment->amount,
                                        2,
                                        ',',
                                        '.'
                                    ) }} €
                                </p>

                                <button
                                    type="button"
                                    wire:click="reprintReceipt({{ $payment->id }})"
                                    wire:confirm="Zahlungsbeleg #{{ $payment->id }} wirklich erneut drucken?"
                                    wire:loading.attr="disabled"
                                    wire:target="reprintReceipt({{ $payment->id }})"
                                    @disabled(! $receiptJob || ! $receiptReprintingEnabled)
                                    class="inline-flex min-w-28 items-center justify-center rounded-lg border border-line px-3 py-2 text-xs font-medium text-dim transition hover:border-accent hover:text-accent disabled:cursor-not-allowed disabled:opacity-40"
                                >
                            <span
                                wire:loading.remove
                                wire:target="reprintReceipt({{ $payment->id }})"
                            >
                                Beleg drucken
                            </span>

                                    <span
                                        wire:loading
                                        wire:target="reprintReceipt({{ $payment->id }})"
                                    >
                                Wird gesendet …
                            </span>
                                </button>

                            </div>

                        </div>

                        @if(! $receiptJob)
                            <p class="mt-2 rounded-lg bg-occupied-soft px-2.5 py-2 text-xs text-occupied">
                                Für diese ältere Zahlung ist kein gespeicherter
                                Beleg-Snapshot vorhanden.
                            </p>
                        @endif

                    </div>

                @empty

                    <p class="px-4 py-8 text-center text-sm text-dim">
                        Keine Zahlungen vorhanden.
                    </p>


                @endforelse


            </div>



        </section>

        {{-- Druckjobs --}}
        <section>

            <h2 class="mb-2 font-display text-lg font-semibold">
                Druckjobs
            </h2>

            <div class="overflow-hidden rounded-2xl border border-line bg-surface">

                @forelse($printJobs as $job)

                    @php
                        $jobStatus = match($job->status) {
                            'printed' => [
                                'text-free',
                                'Gedruckt',
                            ],
                            'failed' => [
                                'text-occupied',
                                'Fehlgeschlagen',
                            ],
                            'printing' => [
                                'text-accent',
                                'Wird gedruckt',
                            ],
                            default => [
                                'text-dim',
                                'Ausstehend',
                            ],
                        };
                    @endphp

                    <div
                        wire:key="print-job-{{ $job->id }}"
                        class="border-b border-line px-4 py-3 last:border-b-0"
                    >
                        <div class="flex items-start justify-between gap-3">

                            <div>

                                <p class="font-medium">
                                    Druckjob #{{ $job->id }}
                                </p>

                                <p class="mt-0.5 text-xs text-dim">
                                    {{ $job->printer?->name ?? 'Kein Drucker' }}

                                    @if($job->productionStation)
                                        ·
                                        {{ $job->productionStation->name }}
                                    @endif
                                </p>

                                <p class="mt-0.5 text-xs text-dim">
                                    {{ $job->created_at->format('d.m.Y H:i') }}
                                </p>

                            </div>

                            <span class="text-sm font-medium {{ $jobStatus[0] }}">
                                {{ $jobStatus[1] }}
                            </span>

                        </div>

                        @if($job->error_message)
                            <p class="mt-2 rounded-lg bg-occupied-soft px-2.5 py-2 text-xs text-occupied">
                                {{ $job->error_message }}
                            </p>
                        @endif

                    </div>

                @empty

                    <p class="px-4 py-8 text-center text-sm text-dim">
                        Keine Druckjobs vorhanden.
                    </p>

                @endforelse

            </div>

        </section>

    </div>

    {{-- Einzelausgaben --}}
    <section class="mt-6">

        <h2 class="mb-2 font-display text-lg font-semibold">
            Druckausgaben
        </h2>

        <div class="overflow-x-auto rounded-2xl border border-line">

            <table class="w-full min-w-[700px] text-sm">

                <thead class="bg-surface-2 text-xs uppercase tracking-wide text-dim">
                <tr>
                    <th class="px-4 py-3 text-left font-medium">
                        Ausgabe
                    </th>

                    <th class="px-4 py-3 text-left font-medium">
                        Typ
                    </th>

                    <th class="px-4 py-3 text-left font-medium">
                        Produkt
                    </th>

                    <th class="px-4 py-3 text-right font-medium">
                        Menge
                    </th>

                    <th class="px-4 py-3 text-left font-medium">
                        Drucker
                    </th>

                    <th class="px-4 py-3 text-left font-medium">
                        Status
                    </th>

                    <th class="px-4 py-3 text-left font-medium">
                        Zeitpunkt
                    </th>
                </tr>
                </thead>

                <tbody class="divide-y divide-line">

                @forelse($printOutputs as $output)

                    <tr wire:key="print-output-{{ $output->id }}">

                        <td class="px-4 py-3">
                            #{{ $output->id }}
                        </td>

                        <td class="px-4 py-3">
                            @switch($output->type)

                                @case('receipt')
                                    <span class="rounded-full bg-free/15 px-2 py-1 text-xs font-medium text-free">
                                        {{ data_get(
                                            $output->payload,
                                            'reprint',
                                            false
                                        )
                                            ? 'Belegkopie'
                                            : 'Zahlungsbeleg' }}
                                    </span>
                                                                @break

                                                            @case('cancellation')
                                                                <span class="rounded-full bg-occupied/15 px-2 py-1 text-xs font-medium text-occupied">
                                        Storno
                                    </span>
                                                                @break

                                                            @default
                                                                <span class="rounded-full bg-accent/15 px-2 py-1 text-xs font-medium text-accent">
                                        Produktion
                                    </span>

                            @endswitch
                        </td>

                        <td class="px-4 py-3">
                            @if($output->type === 'receipt')
                                Zahlung #{{ data_get(
                                    $output->payload,
                                    'payment_id',
                                    '–'
                                ) }}
                            @else
                                {{ $output->orderItem?->product?->name
                                    ?? data_get(
                                        $output->payload,
                                        'name',
                                        '–'
                                    ) }}
                            @endif
                        </td>

                        <td class="px-4 py-3 text-right tabular-nums">
                            {{ $output->quantity }}
                        </td>

                        <td class="px-4 py-3">
                            {{ $output->printer?->name ?? '–' }}
                        </td>

                        <td class="px-4 py-3">
                                <span
                                    @class([
                                        'font-medium',
                                        'text-free' =>
                                            $output->status === 'printed',
                                        'text-occupied' =>
                                            $output->status === 'failed',
                                        'text-accent' =>
                                            $output->status === 'printing',
                                        'text-dim' =>
                                            $output->status === 'pending',
                                    ])
                                >
                                    {{ match($output->status) {
                                        'printed' => 'Gedruckt',
                                        'failed' => 'Fehlgeschlagen',
                                        'printing' => 'Wird gedruckt',
                                        default => 'Ausstehend',
                                    } }}
                                </span>
                        </td>

                        <td class="whitespace-nowrap px-4 py-3 tabular-nums">
                            {{ $output->created_at->format('d.m.Y H:i') }}
                        </td>

                    </tr>

                @empty

                    <tr>
                        <td
                            colspan="7"
                            class="px-4 py-8 text-center text-dim"
                        >
                            Keine einzelnen Druckausgaben vorhanden.
                        </td>
                    </tr>

                @endforelse

                </tbody>

            </table>

        </div>

    </section>

</div>
