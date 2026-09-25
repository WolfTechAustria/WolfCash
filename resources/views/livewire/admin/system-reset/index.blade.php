<div class="max-w-3xl">

    <div class="mb-5">
        <h1 class="font-display text-2xl font-semibold tracking-tight text-occupied">System zurücksetzen</h1>
        <p class="mt-1 text-sm text-dim">
            Für eine neue Veranstaltung (ggf. eines anderen Vereins) oder wenn das System weitergegeben wird.
        </p>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-lg border border-free/40 bg-free-soft px-4 py-2.5 text-sm text-free">
            {{ session('success') }}
        </div>
    @endif

    @error('reset')
        <div class="mb-4 rounded-lg border border-occupied/40 bg-occupied-soft px-4 py-2.5 text-sm text-occupied">
            {{ $message }}
        </div>
    @enderror

    <div class="mb-6 rounded-2xl border border-occupied/40 bg-occupied-soft px-5 py-4 text-sm text-occupied">
        <p class="font-semibold">⚠ Diese Aktion kann nicht rückgängig gemacht werden.</p>
        <p class="mt-1 text-occupied/90">
            Gelöschte Daten sind endgültig weg. Lade dir bei Bedarf vorher einen Export
            der betroffenen Bereiche herunter.
        </p>
    </div>

    @php
        $categories = [
            'sales' => [
                'property' => 'resetSales',
                'label' => 'Umsätze & Bestellungen',
                'description' => 'Bestellungen, Zahlungen, Self-Order-Bestellungen, Tagesabschlüsse, Druckaufträge.',
            ],
            'tables' => [
                'property' => 'resetTables',
                'label' => 'Tische',
                'description' => 'Alle angelegten Tische inkl. QR-Self-Ordering-Zuordnung.',
            ],
            'products' => [
                'property' => 'resetProducts',
                'label' => 'Produkte & Speisekarte',
                'description' => 'Produkte, Kategorien und Produktgruppen.',
            ],
            'printing' => [
                'property' => 'resetPrinting',
                'label' => 'Drucker & Arbeitsplätze',
                'description' => 'Konfigurierte Netzwerkdrucker und Arbeitsplätze/Stationen.',
            ],
            'devices' => [
                'property' => 'resetDevices',
                'label' => 'Geräte',
                'description' => 'Registrierte/freigegebene Kassen-Tablets.',
            ],
            'settings' => [
                'property' => 'resetSettings',
                'label' => 'Einstellungen',
                'description' => 'Self-Ordering-Texte und Belegdruck-Schalter werden auf Standardwerte zurückgesetzt.',
            ],
        ];
    @endphp

    <div class="mb-4 flex justify-end gap-2">
        <button
            type="button"
            wire:click="selectAll"
            class="rounded-full border border-line px-3 py-1.5 text-xs font-medium text-dim transition hover:border-accent hover:text-accent"
        >
            Alle auswählen
        </button>
        <button
            type="button"
            wire:click="deselectAll"
            class="rounded-full border border-line px-3 py-1.5 text-xs font-medium text-dim transition hover:border-accent hover:text-accent"
        >
            Alle abwählen
        </button>
    </div>

    <div class="mb-6 flex flex-col gap-3">
        @foreach($categories as $key => $meta)
            @php
                $isForced = $key === 'sales' && $this->salesForced;

                $counts = $this->previewCounts[$key] ?? null;
            @endphp

            <div class="rounded-2xl border border-line bg-surface p-4">
                <label class="flex items-start gap-3">
                    <input
                        type="checkbox"
                        wire:model.live="{{ $meta['property'] }}"
                        {{ $isForced ? 'disabled' : '' }}
                        class="mt-0.5 h-4 w-4 rounded border-line bg-surface-2 accent-occupied"
                    >
                    <span class="min-w-0 flex-1">
                        <span class="flex flex-wrap items-center gap-2">
                            <span class="font-medium">{{ $meta['label'] }}</span>
                            @if($isForced)
                                <span class="rounded-full bg-occupied/15 px-2 py-0.5 text-xs font-medium text-occupied">
                                    wird automatisch mit einbezogen
                                </span>
                            @endif
                        </span>
                        <span class="mt-0.5 block text-xs text-dim">{{ $meta['description'] }}</span>

                        @if($counts)
                            <span class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-occupied/90">
                                @foreach($counts as $label => $count)
                                    <span>{{ $label }}: <strong class="tabular-nums">{{ $count }}</strong></span>
                                @endforeach
                            </span>
                        @endif
                    </span>
                </label>
            </div>
        @endforeach
    </div>

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-line bg-surface p-4">
        <div>
            <p class="text-sm font-medium">Datenexport (empfohlen)</p>
            <p class="text-xs text-dim">Lädt die Daten der oben ausgewählten Bereiche als JSON-Datei herunter.</p>
        </div>
        <button
            type="button"
            wire:click="exportData"
            class="rounded-xl border border-line px-4 py-2 text-sm font-medium text-dim transition hover:border-accent hover:text-accent"
        >
            Daten exportieren
        </button>
    </div>

    <div class="rounded-2xl border border-occupied/40 bg-surface p-5">
        <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-dim">
            Zum Bestätigen "LÖSCHEN" eintippen
        </label>
        <input
            type="text"
            wire:model.live="confirmationText"
            placeholder="LÖSCHEN"
            class="mb-4 w-full max-w-xs rounded-lg border border-line bg-surface-2 px-3 py-2 text-sm focus:border-occupied focus:outline-none"
        >

        <button
            type="button"
            wire:click="confirmReset"
            wire:confirm="Diese Aktion kann NICHT rückgängig gemacht werden. Wirklich fortfahren?"
            wire:loading.attr="disabled"
            wire:target="confirmReset"
            @disabled(! $this->confirmationValid)
            class="w-full rounded-xl bg-occupied py-3 text-sm font-semibold text-white transition active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-40 sm:w-auto sm:px-6"
        >
            <span wire:loading.remove wire:target="confirmReset">
                Ausgewählte Daten endgültig löschen
            </span>
            <span wire:loading wire:target="confirmReset">
                Wird zurückgesetzt …
            </span>
        </button>
    </div>

</div>
