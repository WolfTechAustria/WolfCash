<div>

    <h1>Tischverwaltung</h1>

    <hr>

    <h2>Neuen Tisch anlegen</h2>

    <form wire:submit="save">

        <div>
            <label>Tischnummer</label>
            <br>

            <input
                type="text"
                wire:model="number"
            >

            @error('number')
            <div>{{ $message }}</div>
            @enderror
        </div>

        <br>

        <div>
            <label>Bezeichnung</label>
            <br>

            <input
                type="text"
                wire:model="name"
            >
        </div>

        <br>

        <button type="submit">
            Tisch anlegen
        </button>

    </form>

    <hr>

    <h2>Vorhandene Tische</h2>

    <table border="1" cellpadding="10">

        <thead>
        <tr>
            <th>Nummer</th>
            <th>Name</th>
            <th>Status</th>
            <th>Aktion</th>
        </tr>
        </thead>

        <tbody>

        @foreach($tables as $table)

            <tr>

                <td>{{ $table->number }}</td>

                <td>{{ $table->name }}</td>

                <td>{{ $table->status }}</td>

                <td>

                    <button
                        wire:click="delete({{ $table->id }})"
                        wire:confirm="Tisch wirklich löschen?"
                    >
                        Löschen
                    </button>

                </td>

            </tr>

        @endforeach

        </tbody>

    </table>

</div>
