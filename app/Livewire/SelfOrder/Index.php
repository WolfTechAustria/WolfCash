<?php

namespace App\Livewire\SelfOrder;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductGroup;
use App\Models\Table;
use App\Models\TableOrderSession;
use App\Models\Setting;
use Livewire\Component;
use App\Models\SelfOrder;
use App\Models\SelfOrderItem;
use Illuminate\Support\Facades\DB;
use App\Services\SelfOrderPaymentService;
use App\Services\StockService;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;



class Index extends Component
{
    public TableOrderSession $tableSession;

    public Table $table;

    public ?int $activeGroup = null;

    public ?int $activeCategory = null;

    public bool $creatingSelfOrder = false;

    public ?int $selfOrderId = null;

    /**
     * Produkt-ID => Warenkorbposition
     */
    public array $cart = [];

    public bool $cartOpen = false;

    /*
     * Eindeutiger Besitzer der Bestandsreservierungen
     * dieses Gast-Warenkorbs.
     */
    #[Locked]
    public string $cartHolder = '';

    public function mount(?string $token = null,?TableOrderSession $tableSession = null): void
    {
        if ($tableSession) {

            /*
             * Diese Variante darf nur über unsere
             * signed Route aufgerufen werden.
             */
            $session = $tableSession;

        } else {

            if (! $token) {
                abort(404);
            }

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
                404,
                'Self-Order-Token wurde nicht gefunden.'
            );
        }

        $session->loadMissing('table');

        abort_unless(
            $session->isUsable(),
            403,
            'Diese Tischbestellung ist nicht mehr verfügbar.'
        );

        abort_unless(
            Setting::selfOrderingEnabled(),
            403,
            'Self Ordering ist momentan nicht verfügbar.'
        );

        abort_unless(
            $session->table->self_order_enabled,
            403,
            'Self Ordering ist für diesen Tisch momentan nicht verfügbar.'
        );

        $this->tableSession = $session;

        $this->table = $session->table;

        session()->put(
            'self_order_table_session_id',
            $session->id
        );

        /*
         * Ab hier deine bereits vorhandene
         * Produktgruppen-/Kategorie-Initialisierung.
         */
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

        /*
         * Begrenzten Bestand über die Reservierung berücksichtigen.
         */
        $this->setCartQuantity(
            $productId,
            $currentQuantity + 1
        );
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

        $this->setCartQuantity(
            $productId,
            (int) $this->cart[$productId]['quantity'] + 1
        );
    }

    public function removeProduct(
        int $productId
    ): void {
        if (! isset(
            $this->cart[$productId]
        )) {
            return;
        }

        $this->setCartQuantity(
            $productId,
            (int) $this->cart[$productId]['quantity'] - 1
        );
    }

    /**
     * Reserviert die gewünschte Menge und übernimmt die tatsächlich
     * verfügbare Menge in den Warenkorb.
     */
    private function setCartQuantity(
        int $productId,
        int $quantity
    ): void {
        $stock = app(StockService::class);

        $granted = $stock->reserve(
            $this->cartHolder(),
            $productId,
            $quantity
        );

        if ($granted <= 0) {
            unset(
                $this->cart[$productId]
            );
        } else {
            $this->cart[$productId]['quantity'] =
                $granted;
        }

        $stock->touch(
            $this->cartHolder()
        );
    }

    private function cartHolder(): string
    {
        if ($this->cartHolder === '') {
            $this->cartHolder =
                'self:'.Str::uuid();
        }

        return $this->cartHolder;
    }

    /**
     * Live-Signal: Bestand hat sich geändert, neu rendern.
     */
    #[On('echo:stock,.stock.changed')]
    public function refreshStock(): void
    {
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
    public function proceedToPayment(SelfOrderPaymentService $paymentService): void
    {
        if (! Setting::selfOrderingEnabled()) {
            $this->addError(
                'cart',
                'Self Ordering wurde inzwischen deaktiviert.'
            );

            return;
        }

        $this->table->refresh();

        if (! $this->table->self_order_enabled) {
            $this->addError(
                'cart',
                'Self Ordering wurde für diesen Tisch deaktiviert.'
            );

            return;
        }

        if ($this->creatingSelfOrder) {
            return;
        }

        if ($this->cart === []) {
            $this->addError(
                'cart',
                'Der Warenkorb ist leer.'
            );

            return;
        }

        if (! $this->tableSession->isUsable()) {
            $this->addError(
                'cart',
                'Diese Tischbestellung ist nicht mehr verfügbar.'
            );

            return;
        }

        $this->creatingSelfOrder = true;

        try {
            $selfOrder = DB::transaction(function (): SelfOrder {
                /*
                 * Tischsession innerhalb der Transaktion nochmals prüfen.
                 */
                $tableSession = \App\Models\TableOrderSession::query()
                    ->whereKey($this->tableSession->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $tableSession->isUsable()) {
                    throw new \RuntimeException(
                        'Diese Tischbestellung ist nicht mehr verfügbar.'
                    );
                }

                $validatedItems = [];

                $total = 0.0;

                foreach ($this->cart as $productId => $cartItem) {
                    $quantity = max(
                        0,
                        (int) ($cartItem['quantity'] ?? 0)
                    );

                    if ($quantity <= 0) {
                        continue;
                    }

                    /*
                     * Produkt IMMER erneut aus der Datenbank laden.
                     *
                     * name/price aus dem Browser-/Livewire-State
                     * werden nicht vertraut.
                     */
                    $product = Product::query()
                        ->whereKey((int) $productId)
                        ->where('is_active', true)
                        ->lockForUpdate()
                        ->first();

                    if (! $product) {
                        throw new \RuntimeException(
                            'Ein Produkt im Warenkorb ist nicht mehr verfügbar.'
                        );
                    }

                    if ($product->isSoldOut()) {
                        throw new \RuntimeException(
                            $product->name
                            .' ist inzwischen ausverkauft.'
                        );
                    }

                    /*
                     * Begrenzten Bestand erneut kontrollieren,
                     * abzüglich der Reservierungen anderer Warenkörbe.
                     */
                    $available = app(StockService::class)
                        ->availableFor(
                            $product,
                            $this->cartHolder()
                        );

                    if (
                        $available !== null
                        && $quantity > $available
                    ) {
                        throw new \RuntimeException(
                            'Von '.$product->name
                            .' sind nur noch '
                            .$available
                            .' Stück verfügbar.'
                        );
                    }

                    $unitPrice = round(
                        (float) $product->price,
                        2
                    );

                    $lineTotal = round(
                        $unitPrice * $quantity,
                        2
                    );

                    $note = trim(
                        mb_substr(
                            (string) ($cartItem['note'] ?? ''),
                            0,
                            500
                        )
                    );

                    $validatedItems[] = [
                        'product_id' => $product->id,
                        'name' => $product->name,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'note' => $note !== ''
                            ? $note
                            : null,
                    ];

                    $total += $lineTotal;
                }

                $total = round(
                    $total,
                    2
                );

                if (
                    $validatedItems === []
                    || $total <= 0
                ) {
                    throw new \RuntimeException(
                        'Der Warenkorb enthält keine verrechenbaren Positionen.'
                    );
                }

                /*
                 * Ab jetzt ist dies der serverseitige Snapshot,
                 * der später bezahlt wird.
                 */
                $selfOrder = SelfOrder::create([
                    'table_order_session_id' =>
                        $tableSession->id,

                    'table_id' =>
                        $tableSession->table_id,

                    'status' =>
                        SelfOrder::STATUS_AWAITING_PAYMENT,

                    'amount' =>
                        $total,

                    'currency' =>
                        'EUR',

                    /*
                     * Zahlungsprovider setzen wir
                     * erst beim Erzeugen des Checkouts.
                     */
                    'payment_provider' =>
                        null,

                    'provider_payment_id' =>
                        null,

                    /*
                     * Falls der Zahlungsvorgang nie abgeschlossen
                     * wird, kann dieser Datensatz später verfallen.
                     */
                    'expires_at' =>
                        now()->addMinutes(15),
                ]);

                foreach ($validatedItems as $item) {
                    SelfOrderItem::create([
                        'self_order_id' =>
                            $selfOrder->id,

                        'product_id' =>
                            $item['product_id'],

                        'name' =>
                            $item['name'],

                        'quantity' =>
                            $item['quantity'],

                        'unit_price' =>
                            $item['unit_price'],

                        'note' =>
                            $item['note'],
                    ]);
                }

                /*
                 * Warenkorb-Reservierungen an die eingefrorene
                 * SelfOrder übergeben, damit sie während der
                 * Zahlung weiter halten.
                 */
                app(StockService::class)
                    ->transferToSelfOrder(
                        $this->cartHolder(),
                        $selfOrder
                    );

                return $selfOrder;
            });

            $this->selfOrderId =
                $selfOrder->id;

            $stripeSession =
                $paymentService
                    ->createCheckoutSession(
                        $selfOrder
                    );


            if (! $stripeSession->url) {
                throw new \RuntimeException(
                    'Stripe hat keine Zahlungs-URL zurückgegeben.'
                );
            }

            $this->cart = [];
            $this->cartOpen = false;

            $this->redirect(
                $stripeSession->url
            );


            /*
             * Der Warenkorb wurde jetzt vollständig und
             * serverseitig als SelfOrder eingefroren.
             *
             * Der lokale Warenkorb darf daher geleert werden.
             */
            $this->cart = [];

            $this->cartOpen = false;

            $this->resetErrorBag('cart');

        } catch (\Throwable $exception) {
            report($exception);

            /*
             * Checkout fehlgeschlagen, der Warenkorb bleibt beim Gast:
             * Reservierungen wieder dem Warenkorb zuordnen.
             */
            if (isset($selfOrder)) {
                app(StockService::class)->release(
                    StockService::selfOrderHolder($selfOrder)
                );

                foreach (array_keys($this->cart) as $productId) {
                    $this->setCartQuantity(
                        (int) $productId,
                        (int) $this->cart[$productId]['quantity']
                    );
                }
            }

            $this->addError(
                'cart',
                $exception->getMessage()
            );
        } finally {
            $this->creatingSelfOrder = false;
        }
    }


    public function render()
    {
        $products = $this->activeCategory
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
            : collect();

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
                    $products,

                /*
                 * Verbleibender Bestand abzüglich aller
                 * Warenkorb-Reservierungen (null = unbegrenzt).
                 */
                'stock' =>
                    app(StockService::class)
                        ->remainingMap($products),
            ]
        )->layout(
            'components.layouts.self-order'
        );
    }
}
