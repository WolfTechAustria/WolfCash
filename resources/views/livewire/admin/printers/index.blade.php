<div>

    <h1>Drucker</h1>

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

        <button type="submit">
            Speichern
        </button>

    </form>

    <hr>

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
