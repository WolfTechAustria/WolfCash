<?php

namespace App\Livewire\Pos\Concerns;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductGroup;
use App\Services\StockService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;

/**
 * Gemeinsame Produktraster- und Warenkorb-Logik für die
 * Kellner-Kasse (Tischauswahl) und die stationäre
 * Selbstbedienungskasse (kein Tisch).
 *
 * Warenkorbmengen werden über den StockService serverseitig
 * reserviert und sind damit für alle Kassen live vom Bestand
 * abgezogen.
 */
trait ManagesProductCart
{
    public array $cart = [];

    public array $orderItems = [];

    public ?int $activeGroup = null;

    public ?int $activeCategory = null;

    /*
     * Eindeutiger Besitzer der Reservierungen dieses Warenkorbs.
     */
    #[Locked]
    public string $cartHolder = '';

    public function setGroup(int $groupId): void
    {
        $this->activeGroup = $groupId;

        /*
         * "Alle" ist der Default beim Gruppenwechsel: alle Produkte
         * der Gruppe über alle Kategorien hinweg, keine Zwangsauswahl
         * einer Unterkategorie mehr.
         */
        $this->activeCategory = null;
    }

    public function setCategory(?int $categoryId): void
    {
        $this->activeCategory = $categoryId;
    }

    public function addProduct(int $productId): void
    {
        $product = Product::query()
            ->findOrFail($productId);

        if ($product->isSoldOut()) {
            return;
        }

        if (! isset($this->cart[$productId])) {
            $this->cart[$productId] = [
                'id' => $product->id,
                'name' => $product->name,
                'price' => $product->price,
                'quantity' => 0,
                'note' => '',
            ];
        }

        $this->setCartQuantity(
            $productId,
            (int) $this->cart[$productId]['quantity'] + 1
        );

        // Nur bei tatsächlich reservierter Menge; die Kellner-Kasse ignoriert das Event bewusst.
        if (isset($this->cart[$productId])) {
            $this->dispatch('cart-item-added');
        }
    }

    public function removeProduct(int $productId): void
    {
        if (! isset($this->cart[$productId])) {
            return;
        }

        $this->setCartQuantity(
            $productId,
            (int) $this->cart[$productId]['quantity'] - 1
        );
    }

    public function increaseProduct(int $productId): void
    {
        if (! isset($this->cart[$productId])) {
            return;
        }

        $this->setCartQuantity(
            $productId,
            (int) $this->cart[$productId]['quantity'] + 1
        );
    }

    /**
     * Reserviert die gewünschte Menge und übernimmt die tatsächlich
     * verfügbare Menge in den Warenkorb.
     */
    protected function setCartQuantity(int $productId, int $quantity): void
    {
        $stock = app(StockService::class);

        $granted = $stock->reserve(
            $this->cartHolder(),
            $productId,
            $quantity
        );

        if ($granted <= 0) {
            unset($this->cart[$productId]);
        } else {
            $this->cart[$productId]['quantity'] = $granted;
        }

        $stock->touch($this->cartHolder());
    }

    /**
     * Leert den Warenkorb und gibt alle Reservierungen frei.
     */
    protected function releaseCart(): void
    {
        if ($this->cartHolder !== '') {
            app(StockService::class)->release($this->cartHolder);
        }

        $this->cart = [];
    }

    protected function cartHolder(): string
    {
        if ($this->cartHolder === '') {
            $this->cartHolder = 'pos:'.Str::uuid();
        }

        return $this->cartHolder;
    }

    /**
     * Live-Signal einer anderen Kasse: nur neu rendern.
     */
    #[On('echo:stock,.stock.changed')]
    public function refreshStock(): void
    {
    }

    public function getTotalProperty(): float
    {
        $total = 0.0;

        /*
         * Bereits bonierte, nicht stornierte Positionen.
         */
        foreach ($this->orderItems as $item) {
            $total +=
                (float) $item['price']
                * (int) $item['open_quantity'];
        }

        /*
         * Noch nicht bonierte Warenkorbpositionen.
         */
        foreach ($this->cart as $item) {
            $total +=
                (float) $item['price']
                * (int) $item['quantity'];
        }

        return round($total, 2);
    }

    /**
     * @return array{
     *     groups: Collection<int, ProductGroup>,
     *     categories: Collection<int, ProductCategory>,
     *     products: Collection<int, Product>,
     *     stock: array<int, int|null>,
     * }
     */
    protected function loadProductCatalog(): array
    {
        if (! $this->activeGroup) {
            $firstGroup = ProductGroup::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->first();

            if ($firstGroup) {
                $this->activeGroup = $firstGroup->id;
            }

            /*
             * Auch beim initialen Laden ist "Alle" der Default —
             * keine Kategorie wird mehr automatisch vorausgewählt.
             */
        }

        $categoryIdsInGroup = $this->activeGroup
            ? ProductCategory::query()
                ->where('product_group_id', $this->activeGroup)
                ->pluck('id')
            : collect();

        $products = match (true) {
            $this->activeCategory !== null => Product::query()
                ->where('product_category_id', $this->activeCategory)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),

            $this->activeGroup !== null => Product::query()
                ->whereIn('product_category_id', $categoryIdsInGroup)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),

            default => collect(),
        };

        return [
            'groups' => ProductGroup::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),

            'categories' => $this->activeGroup
                ? ProductCategory::query()
                    ->where(
                        'product_group_id',
                        $this->activeGroup
                    )
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get()
                : collect(),

            'products' => $products,

            /*
             * Verbleibender Bestand je Produkt (null = unbegrenzt),
             * bereits abzüglich aller Warenkorb-Reservierungen.
             */
            'stock' => app(StockService::class)->remainingMap($products),
        ];
    }
}
