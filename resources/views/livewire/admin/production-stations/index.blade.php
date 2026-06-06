<div>

    <h1>Produktionsstationen</h1>

    <hr>

    <form wire:submit="save">

        <input
            type="text"
            wire:model="name"
            placeholder="Name"
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

            <th>Aktion</th>

        </tr>

        </thead>

        <tbody>

        @foreach($stations as $station)

            <tr>

                <td>
                    {{ $station->name }}
                </td>

                <td>

                    <button
                        wire:click="delete({{ $station->id }})"
                    >
                        Löschen
                    </button>

                </td>

            </tr>

        @endforeach

        </tbody>

    </table>

</div>
