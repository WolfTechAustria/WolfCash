<div>

    <h1>
        @if($editingId)
            Produktgruppe bearbeiten
        @else
            Produktgruppen
        @endif
    </h1>

    <hr>

    <form wire:submit="save">

        <input
            type="text"
            wire:model="name"
            placeholder="Name"
        >

        <input
            type="number"
            wire:model="sort_order"
            placeholder="Sortierung"
        >

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
            <th>Sortierung</th>
            <th>Aktion</th>
        </tr>
        </thead>

        <tbody>

        @foreach($groups as $group)

            <tr>

                <td>
                    {{ $group->name }}
                </td>

                <td>{{ $group->sort_order }}</td>

                <td>
                    <button wire:click="edit({{ $group->id }})">
                        Bearbeiten
                    </button>
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
