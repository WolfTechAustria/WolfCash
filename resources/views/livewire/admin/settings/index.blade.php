<div class="mx-auto max-w-3xl">

    <div class="mb-5">
        <h1 class="font-display text-2xl font-semibold tracking-tight">
            Einstellungen
        </h1>

        <p class="mt-1 text-sm text-dim">
            Globale Einstellungen für das Kassensystem.
        </p>
    </div>

    @if(session('settingsSaved'))
        <div class="mb-4 rounded-xl border border-free/40 bg-free-soft px-4 py-3 text-sm text-free">
            {{ session('settingsSaved') }}
        </div>
    @endif

    <form
        wire:submit="save"
        class="space-y-5"
    >
        <section class="rounded-2xl border border-line bg-surface p-5">

            <div class="mb-5">

                <h2 class="font-display text-lg font-semibold">
                    Drucker
                </h2>

                <p class="mt-1 text-sm text-dim">
                    Welche Drucker Zahlungsbelege und die Bons der
                    stationären Kassen ausgeben. Drucker selbst werden unter
                    <a href="{{ route('admin.printers') }}" class="text-accent hover:underline">Drucker</a>
                    angelegt.
                </p>

            </div>

            <div class="grid gap-4 sm:grid-cols-2">

                <div>
                    <label for="receiptPrinterId" class="mb-2 block text-sm font-medium">
                        Drucker für Zahlungsbelege
                    </label>

                    <select
                        id="receiptPrinterId"
                        wire:model="receiptPrinterId"
                        class="w-full rounded-xl border border-line bg-surface-2 px-3 py-2.5 text-fg outline-none transition focus:border-accent"
                    >
                        <option value="">— kein Drucker —</option>
                        @foreach($printers as $printer)
                            <option value="{{ $printer->id }}">
                                {{ $printer->name }}{{ $printer->is_active ? '' : ' (inaktiv)' }}
                            </option>
                        @endforeach
                    </select>

                    @error('receiptPrinterId')
                        <p class="mt-1 text-xs text-occupied">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="stationaryPrinterId" class="mb-2 block text-sm font-medium">
                        Standard-Bon-Drucker der stationären Kassen
                    </label>

                    <select
                        id="stationaryPrinterId"
                        wire:model="stationaryPrinterId"
                        class="w-full rounded-xl border border-line bg-surface-2 px-3 py-2.5 text-fg outline-none transition focus:border-accent"
                    >
                        <option value="">— kein Drucker —</option>
                        @foreach($printers as $printer)
                            <option value="{{ $printer->id }}">
                                {{ $printer->name }}{{ $printer->is_active ? '' : ' (inaktiv)' }}
                            </option>
                        @endforeach
                    </select>

                    <p class="mt-1 text-xs text-dim">
                        Bestellungen an stationären Kassen werden hier statt an den
                        Stationen gedruckt. Jede Kassa kann unter
                        <a href="{{ route('admin.tables') }}" class="text-accent hover:underline">Tische</a>
                        einen eigenen Drucker bekommen – dort kommen dann auch
                        ihre Zahlungsbelege heraus.
                    </p>

                    @error('stationaryPrinterId')
                        <p class="mt-1 text-xs text-occupied">{{ $message }}</p>
                    @enderror
                </div>

            </div>

        </section>

        <section class="rounded-2xl border border-line bg-surface p-5">

            <div class="mb-5">

                <h2 class="font-display text-lg font-semibold">
                    Zahlungsbelege
                </h2>

                <p class="mt-1 text-sm text-dim">
                    Legt fest, wann Zahlungsbelege automatisch oder
                    manuell gedruckt werden dürfen.
                </p>

            </div>

            <div class="space-y-4">

                {{-- Automatischer Druck --}}
                <div class="flex items-start justify-between gap-5 rounded-xl bg-surface-2 p-4">

                    <div>

                        <p class="font-medium">
                            Automatischer Belegdruck
                        </p>

                        <p class="mt-1 text-sm text-dim">
                            Druckt direkt nach jeder Zahlung automatisch
                            einen Zahlungsbeleg.
                        </p>

                        @if($automaticReceiptPrintingEnabled)
                            <p class="mt-2 text-xs font-medium text-free">
                                Automatischer Druck aktiviert
                            </p>
                        @else
                            <p class="mt-2 text-xs font-medium text-occupied">
                                Automatischer Druck deaktiviert
                            </p>
                        @endif

                    </div>

                    <label class="relative inline-flex shrink-0 cursor-pointer items-center">

                        <input
                            type="checkbox"
                            wire:model.live="automaticReceiptPrintingEnabled"
                            class="peer sr-only"
                        >

                        <span class="h-7 w-12 rounded-full bg-surface transition peer-checked:bg-accent peer-focus-visible:ring-2 peer-focus-visible:ring-accent/40">

                    <span class="absolute left-1 top-1 h-5 w-5 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>

                </span>

                    </label>

                </div>

                {{-- Manueller Druck --}}
                <div class="flex items-start justify-between gap-5 rounded-xl bg-surface-2 p-4">

                    <div>

                        <p class="font-medium">
                            Manueller Belegdruck
                        </p>

                        <p class="mt-1 text-sm text-dim">
                            Erlaubt das spätere Drucken eines Belegs aus
                            der Bestell- oder Zahlungsdetailansicht.
                        </p>

                        @if($receiptReprintingEnabled)
                            <p class="mt-2 text-xs font-medium text-free">
                                Manueller Druck aktiviert
                            </p>
                        @else
                            <p class="mt-2 text-xs font-medium text-occupied">
                                Manueller Druck deaktiviert
                            </p>
                        @endif

                    </div>

                    <label class="relative inline-flex shrink-0 cursor-pointer items-center">

                        <input
                            type="checkbox"
                            wire:model.live="receiptReprintingEnabled"
                            class="peer sr-only"
                        >

                        <span class="h-7 w-12 rounded-full bg-surface transition peer-checked:bg-accent peer-focus-visible:ring-2 peer-focus-visible:ring-accent/40">

                    <span class="absolute left-1 top-1 h-5 w-5 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>

                </span>

                    </label>

                </div>

            </div>

            <div class="mt-4 rounded-xl border border-line px-4 py-3">

                <p class="text-sm font-medium">
                    Empfohlene Einstellung für Vereine
                </p>

                <p class="mt-1 text-xs text-dim">
                    Automatischer Belegdruck aus, manueller Belegdruck ein.
                    Dadurch wird beim Kassieren kein Papier verbraucht,
                    ein Beleg kann an der Hauptkassa aber jederzeit auf
                    Wunsch gedruckt werden.
                </p>

            </div>

            <div class="mt-5 grid gap-5 md:grid-cols-[1fr_auto]">

                <div class="space-y-4">

                    <div>
                        <label for="receiptTitle" class="mb-2 block text-sm font-medium">
                            Überschrift auf dem Zahlungsbeleg
                        </label>

                        <input
                            id="receiptTitle"
                            type="text"
                            wire:model.live.debounce.300ms="receiptTitle"
                            maxlength="32"
                            placeholder="z. B. FF Musterdorf"
                            class="w-full rounded-xl border border-line bg-surface-2 px-3 py-2.5 text-fg outline-none transition focus:border-accent"
                        >

                        <p class="mt-1 text-xs text-dim">
                            Wird groß und fett gedruckt – pro Zeile passen ca. 16 Zeichen.
                            Leer lassen für „Zahlungsbeleg".
                        </p>

                        @error('receiptTitle')
                            <p class="mt-1 text-xs text-occupied">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="receiptIntro" class="mb-2 block text-sm font-medium">
                            Vortext
                        </label>

                        <textarea
                            id="receiptIntro"
                            wire:model.live.debounce.300ms="receiptIntro"
                            rows="3"
                            maxlength="300"
                            placeholder="z. B. Zeltfest 2026&#10;Hauptstraße 1, 1234 Musterdorf"
                            class="w-full rounded-xl border border-line bg-surface-2 px-3 py-2.5 text-fg outline-none transition focus:border-accent"
                        ></textarea>

                        <p class="mt-1 text-xs text-dim">
                            Erscheint zentriert unter der Überschrift, z. B. Veranstaltung,
                            Adresse oder UID. Zeilenumbrüche werden übernommen.
                        </p>

                        @error('receiptIntro')
                            <p class="mt-1 text-xs text-occupied">{{ $message }}</p>
                        @enderror
                    </div>

                </div>

                {{-- Vorschau: 32 Zeichen breit wie der Bondrucker --}}
                <div>
                    <p class="mb-2 text-sm font-medium">Vorschau</p>

                    <div class="w-[19rem] max-w-full rounded-lg bg-white px-4 py-4 font-mono text-[12px] leading-snug text-neutral-900 shadow-inner">
                        <p class="break-words text-center text-[20px] font-bold leading-tight">
                            {{ trim($receiptTitle) !== '' ? trim($receiptTitle) : 'Zahlungsbeleg' }}
                        </p>

                        @if(trim($receiptIntro) !== '')
                            <p class="mt-1 whitespace-pre-line break-words text-center">{{ trim($receiptIntro) }}</p>
                        @endif

                        @if(trim($receiptTitle) !== '')
                            <p class="mt-2 text-center">Zahlungsbeleg</p>
                        @endif

                        <p class="mt-2 overflow-hidden whitespace-nowrap">================================</p>
                        <p>Beleg: #123</p>
                        <p>Tisch: 5</p>
                        <p class="overflow-hidden whitespace-nowrap">--------------------------------</p>
                        <p>2x Bier</p>
                        <p>&nbsp;&nbsp;Summe: 9,00 EUR</p>
                        <p class="overflow-hidden whitespace-nowrap">================================</p>
                        <p>GESAMT: 9,00 EUR</p>
                    </div>
                </div>

            </div>

        </section>

        <section class="rounded-2xl border border-line bg-surface p-5">

            <div class="mb-5">

                <h2 class="font-display text-lg font-semibold">
                    Zahlungsarten
                </h2>

                <p class="mt-1 text-sm text-dim">
                    Legt fest, welche Zahlungsarten an der Kassa
                    angeboten werden. Barzahlung ist immer verfügbar.
                </p>

            </div>

            <div class="flex items-start justify-between gap-5 rounded-xl bg-surface-2 p-4">

                <div>

                    <p class="font-medium">
                        Kartenzahlung
                    </p>

                    <p class="mt-1 text-sm text-dim">
                        Zeigt beim Abrechnen die Buttons für Kartenzahlung
                        über das Kartenterminal der Kassen-App an.
                    </p>

                    @if($cardPaymentEnabled)
                        <p class="mt-2 text-xs font-medium text-free">
                            Kartenzahlung aktiviert
                        </p>
                    @else
                        <p class="mt-2 text-xs font-medium text-occupied">
                            Kartenzahlung deaktiviert – nur Barzahlung
                        </p>
                    @endif

                </div>

                <label class="relative inline-flex shrink-0 cursor-pointer items-center">

                    <input
                        type="checkbox"
                        wire:model.live="cardPaymentEnabled"
                        class="peer sr-only"
                    >

                    <span class="h-7 w-12 rounded-full bg-surface transition peer-checked:bg-accent peer-focus-visible:ring-2 peer-focus-visible:ring-accent/40">

                        <span class="absolute left-1 top-1 h-5 w-5 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>

                    </span>

                </label>

            </div>

            <div class="mt-4 flex items-start justify-between gap-5 rounded-xl bg-surface-2 p-4">

                <div>

                    <p class="font-medium">
                        Bon / Gutschein
                    </p>

                    <p class="mt-1 text-sm text-dim">
                        Kellner können an der stationären Kassa gekaufte Bons
                        als Bezahlung annehmen. Eingelöste Bons zählen nicht
                        noch einmal zum Umsatz, der Bestand wird zurückgebucht.
                    </p>

                    @if($voucherPaymentEnabled)
                        <p class="mt-2 text-xs font-medium text-free">
                            Bon-Zahlung aktiviert
                        </p>
                    @else
                        <p class="mt-2 text-xs font-medium text-occupied">
                            Bon-Zahlung deaktiviert
                        </p>
                    @endif

                </div>

                <label class="relative inline-flex shrink-0 cursor-pointer items-center">

                    <input
                        type="checkbox"
                        wire:model.live="voucherPaymentEnabled"
                        class="peer sr-only"
                    >

                    <span class="h-7 w-12 rounded-full bg-surface transition peer-checked:bg-accent peer-focus-visible:ring-2 peer-focus-visible:ring-accent/40">

                        <span class="absolute left-1 top-1 h-5 w-5 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>

                    </span>

                </label>

            </div>

        </section>

        <section class="rounded-2xl border border-line bg-surface p-5">

            <div class="mb-5">

                <h2 class="font-display text-lg font-semibold">
                    Self Ordering
                </h2>

                <p class="mt-1 text-sm text-dim">
                    Ermöglicht Gästen die Bestellung und Bezahlung
                    direkt am Tisch über einen QR-Code.
                </p>

            </div>


            <div
                class="flex items-start justify-between gap-5 rounded-xl bg-surface-2 p-4"
            >

                <div>

                    <p class="font-medium">
                        Self Ordering aktivieren
                    </p>

                    <p class="mt-1 text-sm text-dim">
                        Aktiviert die öffentliche Bestellfunktion
                        grundsätzlich. Einzelne Tische können
                        zusätzlich separat deaktiviert werden.
                    </p>

                    @if($selfOrderingEnabled)

                        <p class="mt-2 text-xs font-medium text-free">
                            Self Ordering ist global aktiviert
                        </p>

                    @else

                        <p class="mt-2 text-xs font-medium text-occupied">
                            Self Ordering ist global deaktiviert
                        </p>

                    @endif

                </div>


                <label
                    class="relative inline-flex shrink-0 cursor-pointer items-center"
                >

                    <input
                        type="checkbox"
                        wire:model.live="selfOrderingEnabled"
                        class="peer sr-only"
                    >

                    <span
                        class="h-7 w-12 rounded-full bg-surface transition
                       peer-checked:bg-accent
                       peer-focus-visible:ring-2
                       peer-focus-visible:ring-accent/40"
                    >

                <span
                    class="absolute left-1 top-1 h-5 w-5
                           rounded-full bg-white shadow transition
                           peer-checked:translate-x-5"
                ></span>

            </span>

                </label>

            </div>

            <div class="mt-5">
                <label
                    for="selfOrderingTitle"
                    class="mb-2 block text-sm font-medium"
                >
                    Überschrift auf der QR-Tischkarte
                </label>

                <input
                    id="selfOrderingTitle"
                    type="text"

                    wire:model.defer="selfOrderingTitle"

                    maxlength="80"

                    class="w-full rounded-xl border border-line bg-surface-2 px-3 py-2.5 text-fg outline-none transition focus:border-accent"
                >

                <p class="mt-1 text-xs text-dim">
                    Wird auf den QR-Tischkarten für Gäste angezeigt.
                </p>
            </div>

            <div class="mt-4">

                <label
                    for="selfOrderingSubtitle"
                    class="mb-2 block text-sm font-medium"
                >
                    Untertitel auf der QR-Tischkarte
                </label>

                <input
                    id="selfOrderingSubtitle"
                    type="text"

                    wire:model.defer="selfOrderingSubtitle"

                    maxlength="120"

                    class="w-full rounded-xl border border-line bg-surface-2 px-3 py-2.5 text-fg outline-none transition focus:border-accent"
                >

            </div>

        </section>

        <div class="flex justify-end">

            <button
                type="submit"
                wire:loading.attr="disabled"
                wire:target="save"
                class="rounded-xl bg-accent px-5 py-3 text-sm font-semibold text-accent-ink transition active:scale-[0.98] disabled:opacity-50"
            >
                <span
                    wire:loading.remove
                    wire:target="save"
                >
                    Einstellungen speichern
                </span>

                <span
                    wire:loading
                    wire:target="save"
                >
                    Wird gespeichert …
                </span>
            </button>

        </div>

    </form>

    <div class="mt-6 flex items-center justify-between gap-3 rounded-2xl border border-occupied/30 bg-surface p-4">
        <p class="text-sm text-dim">
            Veranstaltung beendet oder das Gerät wird weitergegeben?
        </p>
        <a
            href="{{ route('admin.system-reset') }}"
            class="shrink-0 rounded-full border border-occupied/40 px-3 py-1.5 text-xs font-medium text-occupied transition hover:bg-occupied/10"
        >
            Zur Datenbereinigung →
        </a>
    </div>

</div>
