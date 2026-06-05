<div>

    <h1>Produkte</h1>

    <form wire:submit="save">

        <div>
            <label>Name</label>
            <br>

            <input type="text" wire:model="name">
        </div>

        <br>

        <div>
            <label>Kategorie</label>
            <br>

            <input type="text" wire:model="category">
        </div>

        <br>

        <div>
            <label>Preis</label>
            <br>

            <input type="number"
                   step="0.01"
                   wire:model="price">
        </div>

        <br>

        <button type="submit">
            Produkt speichern
        </button>

    </form>

    <hr>

    <table border="1" cellpadding="10">

        <thead>
        <tr>
            <th>Name</th>
            <th>Kategorie</th>
            <th>Preis</th>
            <th>Aktion</th>
        </tr>
        </thead>

        <tbody>

        @foreach($products as $product)

            <tr>

                <td>{{ $product->name }}</td>

                <td>{{ $product->category }}</td>

                <td>{{ number_format($product->price, 2) }} €</td>

                <td>
                    <button
                        wire:click="delete({{ $product->id }})">
                        Löschen
                    </button>
                </td>

            </tr>

        @endforeach

        </tbody>

    </table>

</div>
