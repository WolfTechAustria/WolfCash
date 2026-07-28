<div>
    <form wire:submit.prevent="login" class="flex flex-col gap-4">

        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-dim">E-Mail</label>
            <input
                type="email"
                wire:model="email"
                placeholder="name@wolfcash.at"
                autofocus
                class="rounded-lg border border-line bg-surface-2 px-3 py-2.5 text-sm placeholder:text-dim/60 focus:border-accent focus:outline-none"
            >
            @error('email')
            <span class="text-xs text-occupied">{{ $message }}</span>
            @enderror
        </div>

        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-dim">Passwort</label>
            <input
                type="password"
                wire:model="password"
                placeholder="••••••••"
                class="rounded-lg border border-line bg-surface-2 px-3 py-2.5 text-sm placeholder:text-dim/60 focus:border-accent focus:outline-none"
            >
            @error('password')
            <span class="text-xs text-occupied">{{ $message }}</span>
            @enderror
        </div>

        <button
            type="submit"
            class="mt-2 rounded-lg bg-accent py-2.5 text-sm font-semibold text-accent-ink transition hover:bg-accent-strong"
        >
            Anmelden
        </button>

    </form>
</div>
