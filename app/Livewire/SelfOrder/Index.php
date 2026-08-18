<?php

namespace App\Livewire\SelfOrder;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductGroup;
use App\Models\Table;
use App\Models\TableOrderSession;
use Livewire\Component;

class Index extends Component
{
    public TableOrderSession $tableSession;

    public Table $table;

    public ?int $activeGroup = null;

    public ?int $activeCategory = null;

    /**
     * Produkt-ID => Warenkorbposition
     */
    public array $cart = [];

    public bool $cartOpen = false;

    public function mount(string $token): void
    {
        $tokenHash = hash(
            'sha256',
            $token
        );

        $session = TableOrderSession::query()
            ->with('table')
            ->where(
                'token_hash',
                $tokenHash
            )
            ->first();

        abort_unless(
            $session !== null,
            404
        );

        abort_unless(
            $session->isUsable(),
            403,
            'Diese Tischbestellung ist nicht mehr verfügbar.'
        );

        $this->tableSession = $session;

        $this->table = $session->table;

        $firstGroup = ProductGroup::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->first();

        if (! $firstGroup) {
            return;
        }

        $this->activeGroup =
            $firstGroup->id;

        $this->activeCategory =
            ProductCategory::query()
                ->where(
                    'product_group_id',
                    $firstGroup->id
                )
                ->orderBy('sort_order')
                ->orderBy('name')
                ->value('id');
    }

    public function setGroup(
        int $groupId
    ): void {
        $groupExists =
            ProductGroup::query()
                ->whereKey($groupId)
                ->exists();

        if (! $groupExists) {
            return;
        }

        $this->activeGroup =
            $groupId;

        $this->activeCategory =
            ProductCategory::query()
                ->where(
                    'product_group_id',
                    $groupId
                )
                ->orderBy('sort_order')
                ->orderBy('name')
                ->value('id');
    }

    public function setCategory(
        int $categoryId
    ): void {
        $category = ProductCategory::query()
            ->whereKey($categoryId)
            ->where(
                'product_group_id',
                $this->activeGroup
            )
            ->first();

        if (! $category) {
            return;
        }

        $this->activeCategory =
            $category->id;
    }

    public function addProduct(
        int $productId
    ): void {
        $product = Product::query()
            ->whereKey($productId)
            ->where('is_active', true)
            ->first();

        if (! $product) {
            return;
        }

        if ($product->isSoldOut()) {
            return;
        }

        /*
         * Produkt muss zur aktuell dargestellten
         * Kategorie gehören.
         */
        if (
            (int) $product->product_category_id
            !== (int) $this->activeCategory
        ) {
            return;
        }

        $currentQuantity = (int) (
            $this->cart[$productId]['quantity']
            ?? 0
        );

        /*
         * Begrenzten Bestand berücksichtigen.
         */
        if (
            ! $product->isUnlimited()
            && $currentQuantity
            >= (int) $product->available_quantity
        ) {
            return;
        }

        if (! isset(
            $this->cart[$productId]
        )) {
            /*
             * Preis stammt ausschließlich vom Server.
             */
            $this->cart[$productId] = [
                'id' =>
                    $product->id,

                'name' =>
                    $product->name,

                'price' =>
                    (float) $product->price,

                'quantity' =>
                    0,

                'note' =>
                    '',
            ];
        }

        $this->cart[$productId]['quantity']++;
    }

    public function increaseProduct(
        int $productId
    ): void {
        if (! isset(
            $this->cart[$productId]
        )) {
            return;
        }

        /*
         * Immer erneut DB prüfen.
         */
        $product = Product::query()
            ->whereKey($productId)
            ->where('is_active', true)
            ->first();

        if (
            ! $product
            || $product->isSoldOut()
        ) {
            return;
        }

        $current =
            (int) $this->cart[$productId]['quantity'];

        if (
            ! $product->isUnlimited()
            && $current
            >= (int) $product->available_quantity
        ) {
            return;
        }

        $this->cart[$productId]['quantity']++;
    }

    public function removeProduct(
        int $productId
    ): void {
        if (! isset(
            $this->cart[$productId]
        )) {
            return;
        }

        $this->cart[$productId]['quantity']--;

        if (
            $this->cart[$productId]['quantity']
            <= 0
        ) {
            unset(
                $this->cart[$productId]
            );
        }
    }

    public function updateNote(
        int $productId,
        string $note
    ): void {
        if (! isset(
            $this->cart[$productId]
        )) {
            return;
        }

        $this->cart[$productId]['note'] =
            trim(
                mb_substr(
                    $note,
                    0,
                    500
                )
            );
    }

    public function openCart(): void
    {
        $this->cartOpen = true;
    }

    public function closeCart(): void
    {
        $this->cartOpen = false;
    }

    public function getCartQuantityProperty(): int
    {
        return (int) collect(
            $this->cart
        )->sum('quantity');
    }

    public function getCartTotalProperty(): float
    {
        return round(
            collect(
                $this->cart
            )->sum(
                fn (array $item): float =>
                    (float) $item['price']
                    * (int) $item['quantity']
            ),
            2
        );
    }

    public function render()
    {
        return view(
            'livewire.self-order.index',
            [
                'groups' =>
                    ProductGroup::query()
                        ->orderBy('sort_order')
                        ->orderBy('name')
                        ->get(),

                'categories' =>
                    $this->activeGroup
                        ? ProductCategory::query()
                        ->where(
                            'product_group_id',
                            $this->activeGroup
                        )
                        ->orderBy('sort_order')
                        ->orderBy('name')
                        ->get()
                        : collect(),

                'products' =>
                    $this->activeCategory
                        ? Product::query()
                        ->where(
                            'product_category_id',
                            $this->activeCategory
                        )
                        ->where(
                            'is_active',
                            true
                        )
                        ->orderBy('sort_order')
                        ->orderBy('name')
                        ->get()
                        : collect(),
            ]
        )->layout(
            'components.layouts.self-order'
        );
    }
}
