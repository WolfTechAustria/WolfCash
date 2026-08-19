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

</div>
