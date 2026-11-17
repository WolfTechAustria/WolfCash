<div class="mx-auto max-w-4xl">

    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="font-display text-2xl font-semibold tracking-tight">
                Benutzer
            </h1>
            <p class="mt-1 text-sm text-dim">
                Zugänge zum Admin-Bereich. Jeder Benutzer hat vollen Zugriff.
            </p>
        </div>

        @unless($showForm)
            <button
                type="button"
                wire:click="create"
                class="self-start rounded-xl bg-accent px-4 py-2.5 text-sm font-semibold text-accent-ink transition hover:bg-accent-strong"
            >
                + Benutzer anlegen
            </button>
        @endunless
    </div>

    @if(session('userSaved'))
        <div class="mb-4 rounded-xl border border-free/40 bg-free-soft px-4 py-3 text-sm text-free">
            {{ session('userSaved') }}
        </div>
    @endif

    @error('delete')
        <div class="mb-4 rounded-xl border border-occupied/40 bg-occupied-soft px-4 py-3 text-sm text-occupied">
            {{ $message }}
        </div>
    @enderror

    @if($showForm)
        <form
            wire:submit="save"
            class="mb-6 rounded-2xl border border-line bg-surface p-5"
        >
            <h2 class="mb-4 font-display text-lg font-semibold">
                {{ $editingId ? 'Benutzer bearbeiten' : 'Neuer Benutzer' }}
            </h2>

            <div class="grid gap-4 sm:grid-cols-2">

                <div>
                    <label for="userName" class="mb-1.5 block text-sm font-medium">Name</label>
                    <input
                        id="userName"
                        type="text"
                        wire:model="name"
                        autocomplete="off"
                        class="w-full rounded-xl border border-line bg-surface-2 px-3 py-2.5 text-sm focus:border-accent focus:outline-none"
                    >
                    @error('name')
                        <p class="mt-1 text-xs text-occupied">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="userEmail" class="mb-1.5 block text-sm font-medium">E-Mail (Anmeldename)</label>
                    <input
                        id="userEmail"
                        type="email"
                        wire:model="email"
                        autocomplete="off"
                        class="w-full rounded-xl border border-line bg-surface-2 px-3 py-2.5 text-sm focus:border-accent focus:outline-none"
                    >
                    @error('email')
                        <p class="mt-1 text-xs text-occupied">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="userPassword" class="mb-1.5 block text-sm font-medium">
                        {{ $editingId ? 'Neues Passwort' : 'Passwort' }}
                    </label>
                    <input
                        id="userPassword"
                        type="password"
                        wire:model="password"
                        autocomplete="new-password"
                        class="w-full rounded-xl border border-line bg-surface-2 px-3 py-2.5 text-sm focus:border-accent focus:outline-none"
                    >
                    <p class="mt-1 text-xs text-dim">
                        Mindestens 8 Zeichen.
                        @if($editingId)
                            Leer lassen, um das bisherige Passwort zu behalten.
                        @endif
                    </p>
                    @error('password')
                        <p class="mt-1 text-xs text-occupied">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="userPasswordConfirmation" class="mb-1.5 block text-sm font-medium">
                        Passwort wiederholen
                    </label>
                    <input
                        id="userPasswordConfirmation"
                        type="password"
                        wire:model="password_confirmation"
                        autocomplete="new-password"
                        class="w-full rounded-xl border border-line bg-surface-2 px-3 py-2.5 text-sm focus:border-accent focus:outline-none"
                    >
                </div>

            </div>

            <div class="mt-5 flex justify-end gap-2">
                <button
                    type="button"
                    wire:click="cancel"
                    class="rounded-xl border border-line px-4 py-2.5 text-sm font-medium text-dim transition hover:text-fg"
                >
                    Abbrechen
                </button>

                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="save"
                    class="rounded-xl bg-accent px-5 py-2.5 text-sm font-semibold text-accent-ink transition hover:bg-accent-strong disabled:opacity-50"
                >
                    {{ $editingId ? 'Speichern' : 'Anlegen' }}
                </button>
            </div>
        </form>
    @endif

    <div class="overflow-x-auto rounded-2xl border border-line">
        <table class="w-full text-sm">
            <thead class="bg-surface-2 text-xs uppercase tracking-wide text-dim">
            <tr>
                <th class="px-4 py-3 text-left font-medium">Name</th>
                <th class="px-4 py-3 text-left font-medium">E-Mail</th>
                <th class="px-4 py-3 text-left font-medium">Angelegt</th>
                <th class="px-4 py-3 text-right font-medium">Aktion</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-line">
            @foreach($users as $user)
                @php $isSelf = $user->id === auth()->id(); @endphp

                <tr
                    wire:key="user-{{ $user->id }}"
                    @class([
                        'transition hover:bg-surface-2/40',
                        'bg-surface-2/40' => $editingId === $user->id,
                    ])
                >
                    <td class="px-4 py-3 font-medium">
                        {{ $user->name }}
                        @if($isSelf)
                            <span class="ml-1 rounded-full bg-accent/15 px-2 py-0.5 text-xs font-medium text-accent">Du</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-dim">{{ $user->email }}</td>
                    <td class="px-4 py-3 tabular-nums text-dim">{{ $user->created_at?->format('d.m.Y') }}</td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex justify-end gap-2">
                            <button
                                type="button"
                                wire:click="edit({{ $user->id }})"
                                class="rounded-full border border-line px-3 py-1 text-xs font-medium text-dim transition hover:border-accent hover:text-accent"
                            >
                                Bearbeiten
                            </button>

                            @unless($isSelf)
                                <button
                                    type="button"
                                    wire:click="delete({{ $user->id }})"
                                    wire:confirm="Benutzer {{ $user->name }} wirklich löschen? Die Person kann sich danach nicht mehr anmelden."
                                    @disabled($users->count() <= 1)
                                    class="rounded-full border border-occupied/40 px-3 py-1 text-xs font-medium text-occupied transition hover:bg-occupied/10 disabled:opacity-40"
                                >
                                    Löschen
                                </button>
                            @endunless
                        </div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <p class="mt-3 text-xs text-dim">
        Beim Löschen bleiben Bestellungen, Zahlungen und Stornos erhalten; dort steht dann kein Benutzer mehr.
        Im Protokoll bleibt der Name sichtbar.
    </p>

</div>
