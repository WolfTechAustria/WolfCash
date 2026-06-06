<div>

    <h1>Produktkategorien</h1>

    <hr>

    <form wire:submit="save">

        <select wire:model="product_group_id">

            <option value="">
                Gruppe wählen
            </option>

            @foreach($groups as $group)

                <option value="{{ $group->id }}">
                    {{ $group->name }}
                </option>

            @endforeach

        </select>

        <select wire:model="printer_id">

            <option value="">
                Drucker wählen
            </option>

            @foreach($printers as $printer)

                <option value="{{ $printer->id }}">
                    {{ $printer->name }}
                </option>

            @endforeach

        </select>

        <select wire:model="production_station_id">

            <option value="">
                Produktionsstation wählen
            </option>

            @foreach($stations as $station)

                <option value="{{ $station->id }}">
                    {{ $station->name }}
                </option>

            @endforeach

        </select>

        <input
            type="text"
            wire:model="name"
            placeholder="Kategorie"
        >

        <button type="submit">
            Speichern
        </button>

    </form>

    <hr>

    <table border="1" cellpadding="10">

        <thead>

        <tr>

            <th>Gruppe</th>
            <th>Drucker</th>
            <th>Station</th>
            <th>Name</th>
            <th>Aktion</th>

        </tr>

        </thead>

        <tbody>

        @foreach($categories as $category)

            <tr>

                <td>
                    {{ $category->group?->name }}
                </td>

                <td>
                    {{ $category->printer?->name }}
                </td>

                <td>
                    {{ $category->productionStation?->name }}
                </td>

                <td>
                    {{ $category->name }}
                </td>

                <td>

                    <button
                        wire:click="delete({{ $category->id }})"
                    >
                        Löschen
                    </button>

                </td>

            </tr>

        @endforeach

        </tbody>

    </table>

</div>
