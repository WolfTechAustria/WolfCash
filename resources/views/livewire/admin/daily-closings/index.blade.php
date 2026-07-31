<div>

    <div class="mb-5 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">

        <div>
            <h1 class="font-display text-2xl font-semibold tracking-tight">
                Tagesabschlüsse
            </h1>

            <p class="mt-1 text-sm text-dim">
                Historie aller dauerhaft gespeicherten Geschäftstage.
            </p>
        </div>

        <div class="w-full md:w-48">

            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-dim">
                Jahr
            </label>

            <select
                wire:model.live="year"
                class="w-full rounded-xl border border-line bg-surface px-3 py-2.5 text-sm focus:border-accent focus:outline-none"
            >
                <option value="all">Alle Jahre</option>

                @foreach($years as $yearOption)
                    <option value="{{ $yearOption }}">
                        {{ $yearOption }}
                    </option>
                @endforeach
            </select>

        </div>

    </div>

    {{-- Kennzahlen --}}
    <div class="mb-5 grid grid-cols-2 gap-2.5 lg:grid-cols-5">

        <div class="rounded-xl border border-line bg-surface p-3">
            <p class="text-xs font-medium uppercase tracking-wide text-dim">
                Abschlüsse
            </p>

            <p class="mt-1 font-display text-xl font-semibold tabular-nums">
                {{ $summary['count'] }}
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

        <div class="rounded-xl border border-line bg-surface p-3">
            <p class="text-xs font-medium uppercase tracking-wide text-dim">
                Bar
            </p>

            <p class="mt-1 font-display text-xl font-semibold tabular-nums">
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

            <p class="mt-1 font-display text-xl font-semibold tabular-nums">
                {{ number_format(
                    $summary['card_amount'],
                    2,
                    ',',
                    '.'
                ) }} €
            </p>
        </div>

        <div class="col-span-2 rounded-xl border border-occupied/40 bg-occupied-soft p-3 lg:col-span-1">
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

    {{-- Mobile Ansicht --}}
    <div class="space-y-3 md:hidden">

        @forelse($closings as $closing)

            @php
                $user = $closing->closedByUser;

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

            <article
                wire:key="mobile-closing-{{ $closing->id }}"
                class="rounded-2xl border border-line bg-surface p-4"
            >
                <div class="flex items-start justify-between gap-3">

                    <div>
                        <p class="font-display text-lg font-semibold">
                            {{ $closing->business_date->format('d.m.Y') }}
                        </p>

                        <p class="mt-0.5 text-sm text-dim">
                            Abschluss #{{ $closing->id }}
                        </p>
                    </div>

                    <span class="rounded-full bg-free/15 px-2.5 py-1 text-xs font-medium text-free">
                        Abgeschlossen
                    </span>

                </div>

                <div class="mt-4 grid grid-cols-2 gap-2">

                    <div class="rounded-xl bg-surface-2 p-3">
                        <p class="text-xs text-dim">Bezahlt</p>

                        <p class="mt-1 font-medium tabular-nums text-free">
                            {{ number_format(
                                $closing->paid_amount,
                                2,
                                ',',
                                '.'
                            ) }} €
                        </p>
                    </div>

                    <div class="rounded-xl bg-surface-2 p-3">
                        <p class="text-xs text-dim">Storniert</p>

                        <p class="mt-1 font-medium tabular-nums text-occupied">
                            {{ number_format(
                                $closing->cancelled_amount,
                                2,
                                ',',
                                '.'
                            ) }} €
                        </p>
                    </div>

                    <div class="rounded-xl bg-surface-2 p-3">
                        <p class="text-xs text-dim">Bar</p>

                        <p class="mt-1 font-medium tabular-nums">
                            {{ number_format(
                                $closing->cash_amount,
                                2,
                                ',',
                                '.'
                            ) }} €
                        </p>
                    </div>

                    <div class="rounded-xl bg-surface-2 p-3">
                        <p class="text-xs text-dim">Karte</p>

                        <p class="mt-1 font-medium tabular-nums">
                            {{ number_format(
                                $closing->card_amount,
                                2,
                                ',',
                                '.'
                            ) }} €
                        </p>
                    </div>

                </div>

                <div class="mt-3 flex items-end justify-between gap-3">

                    <div class="text-xs text-dim">
                        <p>
                            {{ $closing->closed_at->format('d.m.Y H:i') }}
                        </p>

                        <p class="mt-0.5">
                            {{ $userName !== ''
                                ? $userName
                                : 'Nicht zugeordnet' }}
                        </p>
                    </div>

                    <a
                        href="{{ route(
                            'admin.daily-closings.show',
                            $closing
                        ) }}"
                        class="text-sm font-medium text-accent"
                    >
                        Details →
                    </a>

                </div>

            </article>

        @empty

            <div class="rounded-2xl border border-line bg-surface px-5 py-10 text-center text-dim">
                Keine Tagesabschlüsse gefunden.
            </div>

        @endforelse

    </div>

    {{-- Desktop-Tabelle --}}
    <div class="hidden overflow-x-auto rounded-2xl border border-line md:block">

        <table class="w-full min-w-[950px] text-sm">

            <thead class="bg-surface-2 text-xs uppercase tracking-wide text-dim">
            <tr>
                <th class="px-4 py-3 text-left font-medium">
                    Geschäftstag
                </th>

                <th class="px-4 py-3 text-left font-medium">
                    Abschluss
                </th>

                <th class="px-4 py-3 text-right font-medium">
                    Bestellungen
                </th>

                <th class="px-4 py-3 text-right font-medium">
                    Bezahlt
                </th>

                <th class="px-4 py-3 text-right font-medium">
                    Bar
                </th>

                <th class="px-4 py-3 text-right font-medium">
                    Karte
                </th>

                <th class="px-4 py-3 text-right font-medium">
                    Storniert
                </th>

                <th class="px-4 py-3 text-left font-medium">
                    Mitarbeiter
                </th>

                <th class="px-4 py-3 text-right font-medium">
                    Aktion
                </th>
            </tr>
            </thead>

            <tbody class="divide-y divide-line">

            @forelse($closings as $closing)

                @php
                    $user = $closing->closedByUser;

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

                <tr
                    wire:key="closing-{{ $closing->id }}"
                    class="transition hover:bg-surface-2/40"
                >
                    <td class="px-4 py-3 font-medium">
                        {{ $closing->business_date->format('d.m.Y') }}
                    </td>

                    <td class="whitespace-nowrap px-4 py-3 text-dim">
                        #{{ $closing->id }}
                        ·
                        {{ $closing->closed_at->format('H:i') }}
                    </td>

                    <td class="px-4 py-3 text-right tabular-nums">
                        {{ $closing->orders_total }}
                    </td>

                    <td class="px-4 py-3 text-right font-medium tabular-nums text-free">
                        {{ number_format(
                            $closing->paid_amount,
                            2,
                            ',',
                            '.'
                        ) }} €
                    </td>

                    <td class="px-4 py-3 text-right tabular-nums">
                        {{ number_format(
                            $closing->cash_amount,
                            2,
                            ',',
                            '.'
                        ) }} €
                    </td>

                    <td class="px-4 py-3 text-right tabular-nums">
                        {{ number_format(
                            $closing->card_amount,
                            2,
                            ',',
                            '.'
                        ) }} €
                    </td>

                    <td class="px-4 py-3 text-right tabular-nums text-occupied">
                        {{ number_format(
                            $closing->cancelled_amount,
                            2,
                            ',',
                            '.'
                        ) }} €
                    </td>

                    <td class="px-4 py-3">
                        {{ $userName !== ''
                            ? $userName
                            : 'Nicht zugeordnet' }}
                    </td>

                    <td class="px-4 py-3 text-right">
                        <a
                            href="{{ route(
                                    'admin.daily-closings.show',
                                    $closing
                                ) }}"
                            class="inline-flex rounded-lg border border-line px-3 py-1.5 text-xs font-medium text-dim transition hover:border-accent hover:text-accent"
                        >
                            Details
                        </a>
                    </td>
                </tr>

            @empty

                <tr>
                    <td
                        colspan="9"
                        class="px-4 py-10 text-center text-dim"
                    >
                        Keine Tagesabschlüsse gefunden.
                    </td>
                </tr>

            @endforelse

            </tbody>

        </table>

    </div>

    @if($closings->hasPages())
        <div class="mt-5">
            {{ $closings->links() }}
        </div>
    @endif

</div>
