<div>

    <h1>
        @if($editingId)
            Produktionsstation bearbeiten
        @else
            Produktionsstationen
        @endif
    </h1>
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

    @error('delete')
    <div style="color:red;">
        {{ $message }}
    </div>
    @enderror

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

                    <button wire:click="edit({{ $station->id }})">
                        Bearbeiten
                    </button>

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
