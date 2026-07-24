<div>


    <h1>
        @if($editingId)
            Drucker bearbeiten
        @else
            Drucker
        @endif
    </h1>

        @if(session('success'))
            <div class="mb-4 rounded bg-green-100 border border-green-300 p-3 text-green-800">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="mb-4 rounded bg-red-100 border border-red-300 p-3 text-red-800">
                {{ session('error') }}
            </div>
        @endif

    <hr>

    <form wire:submit="save">

        <input
            type="text"
            wire:model="name"
            placeholder="Name"
        >

        <input
            type="text"
            wire:model="ip_address"
            placeholder="IP-Adresse"
        >

            <label class="block text-sm font-medium">
                Druckzeitpunkt
            </label>

            <select
                wire:model="print_trigger"
                class="mt-1 w-full rounded border-gray-300"
            >
                <option value="immediate">
                    Sofort beim Bonieren
                </option>

                <option value="on_job_complete">
                    Erst wenn der gesamte Produktionsbon fertig ist
                </option>

                <option value="{{ \App\Models\Printer::PRINT_TRIGGER_ON_ITEM_COMPLETE }}">
                    Sobald eine Position fertig ist
                </option>
            </select>

            @error('print_trigger')
            <div class="mt-1 text-sm text-red-600">
                {{ $message }}
            </div>
            @enderror

        <button type="submit">
            Speichern
        </button>

    </form>

    <hr>

    @error('delete')
    <div style="color:red;">
        {{ $message }}
    </div>
    @enderror

    <table border="1" cellpadding="10">

        <thead>

        <tr>

            <th>Name</th>
            <th>IP</th>
            <th>Status</th>
            <th>Aktion</th>

        </tr>

        </thead>

        <tbody>

        @foreach($printers as $printer)

            <tr>

                <td>
                    {{ $printer->name }}
                </td>

                <td>
                    {{ $printer->ip_address }}
                </td>

                <td>

                    @if($printer->is_active)

                        Aktiv

                    @else

                        Inaktiv

                    @endif

                </td>

                <td>

                    <button
                        wire:click="toggle({{ $printer->id }})"
                    >
                        Status
                    </button>

                    <button wire:click="edit({{ $printer->id }})">
                        Bearbeiten
                    </button>

                    <button
                        wire:click="testPrinter({{ $printer->id }})"
                        class="btn btn-secondary"
                    >
                        🖨 Testdruck
                    </button>

                    <button
                        wire:click="delete({{ $printer->id }})"
                    >
                        Löschen
                    </button>


                </td>

            </tr>

        @endforeach

        </tbody>

    </table>

</div>
