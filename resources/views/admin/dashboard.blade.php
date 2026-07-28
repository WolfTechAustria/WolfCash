<x-layouts.app>

    <h1 class="mb-6 font-display text-2xl font-semibold tracking-tight">Admin-Übersicht</h1>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">

        <a href="/admin/devices" class="rounded-2xl border border-line bg-surface p-5 transition hover:border-accent">
            <p class="font-display text-lg font-semibold">Geräte</p>
            <p class="mt-1 text-sm text-dim">Kassen-Terminals verwalten und freigeben</p>
        </a>

        <a href="/pos" class="rounded-2xl border border-accent/40 bg-accent/10 p-5 transition hover:border-accent">
            <p class="font-display text-lg font-semibold text-accent">POS öffnen</p>
            <p class="mt-1 text-sm text-dim">Zur Kassenansicht wechseln</p>
        </a>

    </div>

</x-layouts.app>
