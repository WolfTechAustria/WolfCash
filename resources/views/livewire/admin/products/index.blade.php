<div>

    <h1>

        @if($editingId)

            Produkt bearbeiten

        @else

            Neues Produkt

        @endif

    </h1>

    <form wire:submit="save">

        <input
            type="text"
            wire:model.live="search"
            placeholder="Produkt suchen..."
        >

        <div>

            <label>Name</label>

            <br>

            <input
                type="text"
                wire:model="name"
            >

        </div>

        <br>

        <div>

            <label>Kategorie</label>

            <br>

            <select wire:model="product_category_id">

                <option value="">
                    Kategorie wählen
                </option>

                @foreach($categories as $category)

                    <option value="{{ $category->id }}">

                        {{ $category->group?->name }}

                        →

                        {{ $category->name }}

                    </option>

                @endforeach

            </select>

        </div>

        <br>

        <div>

            <label>Preis</label>

            <br>

            <input
                type="number"
                step="0.01"
                wire:model="price"
            >

        </div>

        <br>

        <div>
            <label>Sortierung</label>
            <br>

            <input
                type="number"
                wire:model="sort_order"
            >
        </div>

        <br>

        <div>

            <label>Druckmodus</label>

            <br>

            <select wire:model="print_mode">

                <option value="grouped">
                    Sammelbon
                </option>

                <option value="split">
                    Einzelbons
                </option>

                <option value="no_print">
                    Nicht drucken
                </option>

            </select>

        </div>

        <br>

        <div>

            <label>Verfügbarer Bestand</label>

            <br>

            <input
                type="number"
                wire:model="available_quantity"
            >

        </div>

        <br>

        <div>

            <label>

                <input
                    type="checkbox"
                    wire:model="is_active"
                >

                Produkt aktiv

            </label>

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

            <th>Gruppe</th>

            <th>Kategorie</th>

            <th>Drucker</th>

            <th>Station</th>

            <th>Preis</th>

            <th>Print Mode</th>

            <th>Bestand</th>

            <th>Sortierung</th>

            <th>Status</th>

            <th>Aktion</th>

        </tr>

        </thead>

        <tbody>

        @foreach($products as $product)

            <tr>

                <td>
                    {{ $product->name }}
                </td>

                <td>
                    {{ $product->category?->group?->name }}
                </td>

                <td>
                    {{ $product->category?->name }}
                </td>

                <td>
                    {{ $product->category?->printer?->name }}
                </td>

                <td>
                    {{ $product->category?->productionStation?->name }}
                </td>

                <td>
                    {{ number_format($product->price, 2) }} €
                </td>

                <td>
                    @if($product->print_mode === 'grouped')
                        Sammelbon
                    @endif

                    @if($product->print_mode === 'split')
                        Einzelbons
                    @endif

                    @if($product->print_mode === 'no_print')
                        Kein Druck
                    @endif
                </td>

                <td>
                    @if($product->available_quantity === -1)

                        Unbegrenzt

                    @else

                        {{ $product->available_quantity }}

                    @endif
                </td>

                <td>
                    {{ $product->sort_order }}
                </td>

                <td>
                    @if($product->is_active)
                        <span style="color:green;font-weight:bold;">Aktiv</span>
                    @else
                        <span style="color:red;font-weight:bold;">Inaktiv</span>
                    @endif

                    <br>

                    <button wire:click="toggleActive({{ $product->id }})">
                        Umschalten
                    </button>
                </td>

                <td>

                    <button
                        wire:click="toggleActive({{ $product->id }})"
                    >
                        Status ändern
                    </button>

                    <button
                        wire:click="edit({{ $product->id }})"
                    >
                        Bearbeiten
                    </button>

                    <button
                        wire:click="delete({{ $product->id }})"
                    >
                        Löschen
                    </button>

                </td>

            </tr>

        @endforeach

        </tbody>

    </table>

</div>
