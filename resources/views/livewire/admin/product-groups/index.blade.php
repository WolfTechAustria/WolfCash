<div>

    <h1>Produktgruppen</h1>

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

        @foreach($groups as $group)

            <tr>

                <td>
                    {{ $group->name }}
                </td>

                <td>

                    <button
                        wire:click="delete({{ $group->id }})"
                    >
                        Löschen
                    </button>

                </td>

            </tr>

        @endforeach

        </tbody>

    </table>

</div>
