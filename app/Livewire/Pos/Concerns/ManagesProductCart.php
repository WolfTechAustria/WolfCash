<?php

namespace App\Livewire\Pos\Concerns;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductGroup;
use Illuminate\Support\Collection;

/**
 * Gemeinsame Produktraster- und Warenkorb-Logik für die
 * Kellner-Kasse (Tischauswahl) und die stationäre
 * Selbstbedienungskasse (kein Tisch).
 */
trait ManagesProductCart
{
    public array $cart = [];

    public array $orderItems = [];

    public ?int $activeGroup = null;

    public ?int $activeCategory = null;

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

        $this->cart[$productId]['quantity']++;
    }

    public function removeProduct(int $productId): void
    {
        if (! isset($this->cart[$productId])) {
            return;
        }

        $this->cart[$productId]['quantity']--;

        if ($this->cart[$productId]['quantity'] <= 0) {
            unset($this->cart[$productId]);
        }
    }

    public function increaseProduct(int $productId): void
    {
        if (! isset($this->cart[$productId])) {
            return;
        }

        $this->cart[$productId]['quantity']++;
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

            'products' => match (true) {
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
            },
        ];
    }
}
