<div>

    @php
        /*
         * Farbe je Ereignis: Rot für Eingriffe (Storno, Sperre, Reset,
         * fehlgeschlagene Anmeldung), Grün für Geldfluss, sonst neutral.
         */
        $badgeClass = fn (string $event) => match ($event) {
            \App\Models\ActivityLog::ITEM_CANCELLED,
            \App\Models\ActivityLog::DEVICE_BLOCKED,
            \App\Models\ActivityLog::PRODUCT_DELETED,
            \App\Models\ActivityLog::SYSTEM_RESET,
            \App\Models\ActivityLog::LOGIN_FAILED =>
                'bg-occupied/15 text-occupied',

            \App\Models\ActivityLog::PAYMENT_CREATED,
            \App\Models\ActivityLog::DAILY_CLOSING_CREATED =>
                'bg-free/15 text-free',

            \App\Models\ActivityLog::ORDER_BOOKED =>
                'bg-accent/15 text-accent',

            default =>
                'bg-surface-2 text-dim',
        };
    @endphp

    {{-- Kopf --}}
    <div class="mb-5">

        <h1 class="font-display text-2xl font-semibold tracking-tight">
            Protokoll
        </h1>

        <p class="mt-1 text-sm text-dim">
            Wer hat wann was getan: Buchungen, Stornos, Zahlungen und Änderungen im Backoffice.
        </p>

    </div>

    {{-- Filter --}}
    <div class="mb-5 rounded-2xl border border-line bg-surface p-4">

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">

            <div>
                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-dim">
                    Zeitraum
                </label>

                <select
                    wire:model.live="period"
                    class="w-full rounded-xl border border-line bg-surface-2 px-3 py-2.5 text-sm focus:border-accent focus:outline-none"
                >
                    <option value="today">Heute</option>
                    <option value="yesterday">Gestern</option>
                    <option value="week">Diese Woche</option>
                    <option value="month">Dieser Monat</option>
                    <option value="all">Alle</option>
                </select>
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-dim">
                    Ereignis
                </label>

                <select
                    wire:model.live="event"
                    class="w-full rounded-xl border border-line bg-surface-2 px-3 py-2.5 text-sm focus:border-accent focus:outline-none"
                >
                    <option value="">Alle Ereignisse</option>

                    @foreach($events as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-dim">
                    Benutzer
                </label>

                <select
                    wire:model.live="userId"
                    class="w-full rounded-xl border border-line bg-surface-2 px-3 py-2.5 text-sm focus:border-accent focus:outline-none"
                >
                    <option value="">Alle Benutzer</option>

                    @foreach($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-dim">
                    Gerät
                </label>

                <select
                    wire:model.live="deviceId"
                    class="w-full rounded-xl border border-line bg-surface-2 px-3 py-2.5 text-sm focus:border-accent focus:outline-none"
                >
                    <option value="">Alle Geräte</option>

                    @foreach($devices as $device)
                        <option value="{{ $device->id }}">{{ $device->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-dim">
                    Suche
                </label>

                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Text, Benutzer, Gerät"
                    class="w-full rounded-xl border border-line bg-surface-2 px-3 py-2.5 text-sm placeholder:text-dim/70 focus:border-accent focus:outline-none"
                >
            </div>

            <div class="flex items-end">
                <button
                    type="button"
                    wire:click="resetFilters"
                    class="w-full rounded-xl border border-line px-4 py-2.5 text-sm font-medium text-dim transition hover:border-accent hover:text-accent"
                >
                    Filter zurücksetzen
                </button>
            </div>

        </div>

    </div>

    {{-- Einträge --}}
    <div class="overflow-hidden rounded-2xl border border-line">

        <ul class="divide-y divide-line">

            @forelse($entries as $entry)

                <li wire:key="activity-{{ $entry->id }}">

                    <button
                        type="button"
                        wire:click="toggle({{ $entry->id }})"
                        class="flex w-full flex-col gap-2 px-4 py-3 text-left transition hover:bg-surface-2/40 md:flex-row md:items-center md:gap-4"
                    >
                        <span class="shrink-0 text-xs tabular-nums text-dim md:w-32">
                            {{ $entry->created_at?->format('d.m.Y H:i:s') }}
                        </span>

                        <span class="shrink-0 md:w-44">
                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $badgeClass($entry->event) }}">
                                {{ \App\Models\ActivityLog::eventLabel($entry->event) }}
                            </span>
                        </span>

                        <span class="min-w-0 flex-1 text-sm">
                            {{ $entry->description }}
                        </span>

                        <span class="shrink-0 text-xs text-dim md:w-48 md:text-right">
                            {{ $entry->actor_label }}
                        </span>
                    </button>

                    @if($expandedId === $entry->id)
                        <div class="border-t border-line bg-surface-2/40 px-4 py-3 text-xs">

                            <dl class="grid gap-x-4 gap-y-1 sm:grid-cols-[8rem_1fr]">
                                <dt class="text-dim">Ereignis</dt>
                                <dd class="font-mono">{{ $entry->event }}</dd>

                                @if($entry->subject_type)
                                    <dt class="text-dim">Objekt</dt>
                                    <dd class="font-mono">{{ class_basename($entry->subject_type) }} #{{ $entry->subject_id }}</dd>
                                @endif

                                @if($entry->ip_address)
                                    <dt class="text-dim">IP-Adresse</dt>
                                    <dd class="font-mono">{{ $entry->ip_address }}</dd>
                                @endif
                            </dl>

                            @if($entry->properties)
                                <pre class="mt-3 overflow-x-auto rounded-xl border border-line bg-surface p-3 font-mono text-[11px] leading-relaxed">{{ json_encode($entry->properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                            @endif

                        </div>
                    @endif

                </li>

            @empty

                <li class="px-5 py-10 text-center text-dim">
                    Keine Einträge für den gewählten Filter gefunden.
                </li>

            @endforelse

        </ul>

    </div>

    @if($entries->hasPages())

        <div class="mt-5">
            {{ $entries->links() }}
        </div>

    @endif

</div>
