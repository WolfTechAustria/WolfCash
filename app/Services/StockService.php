<?php

namespace App\Services;

use App\Events\ProductStockChanged;
use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Models\ProductReservation;
use App\Models\SelfOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Zentrale Bestandslogik.
 *
 * Angezeigter Stand = available_quantity − aktive Reservierungen.
 * Warenkörbe (Kellner, stationäre Kasse, Self-Order) reservieren
 * ihre Mengen, Bonieren bzw. bestätigte Zahlung bucht endgültig ab.
 * available_quantity = -1 bedeutet unbegrenzt, dafür wird nie
 * reserviert.
 */
class StockService
{
    /**
     * Reservierungen verfallen nach dieser Inaktivitätszeit.
     */
    public const TTL_MINUTES = 15;

    /**
     * Haltezeit einer an eine Self-Order übergebenen Reservierung,
     * deckt Self-Order-Frist (15 Min.) plus Stripe-Checkout ab.
     */
    public const SELF_ORDER_TTL_MINUTES = 60;

    /**
     * Verbleibender Bestand je Produkt.
     *
     * @param  iterable<Product>  $products
     * @return array<int, int|null> null = unbegrenzt
     */
    public function remainingMap(iterable $products): array
    {
        $limitedIds = [];

        foreach ($products as $product) {
            if (! $product->isUnlimited()) {
                $limitedIds[] = $product->id;
            }
        }

        $reserved = $limitedIds === []
            ? []
            : ProductReservation::query()
                ->active()
                ->whereIn('product_id', $limitedIds)
                ->groupBy('product_id')
                ->selectRaw('product_id, SUM(quantity) AS reserved')
                ->pluck('reserved', 'product_id')
                ->all();

        $map = [];

        foreach ($products as $product) {
            $map[$product->id] = $product->isUnlimited()
                ? null
                : max(
                    0,
                    (int) $product->available_quantity
                    - (int) ($reserved[$product->id] ?? 0)
                );
        }

        return $map;
    }

    /**
     * Setzt die reservierte Menge eines Holders für ein Produkt.
     *
     * @return int tatsächlich reservierte bzw. erlaubte Menge
     */
    public function reserve(string $holder, int $productId, int $quantity): int
    {
        $quantity = max(0, $quantity);

        $granted = DB::transaction(function () use ($holder, $productId, $quantity): int {
            $product = Product::query()
                ->whereKey($productId)
                ->lockForUpdate()
                ->first();

            if (! $product) {
                return 0;
            }

            if ($product->isUnlimited()) {
                return $quantity;
            }

            $allowed = min(
                $quantity,
                max(0, (int) $product->available_quantity - $this->reservedByOthers($productId, $holder))
            );

            if ($allowed <= 0) {
                ProductReservation::query()
                    ->where('product_id', $productId)
                    ->where('holder', $holder)
                    ->delete();

                return 0;
            }

            ProductReservation::query()->updateOrCreate(
                ['product_id' => $productId, 'holder' => $holder],
                ['quantity' => $allowed, 'expires_at' => now()->addMinutes(self::TTL_MINUTES)]
            );

            return $allowed;
        });

        $this->broadcast([$productId]);

        return $granted;
    }

    /**
     * Verlängert alle Reservierungen eines Holders (Warenkorbaktivität).
     */
    public function touch(string $holder): void
    {
        ProductReservation::query()
            ->where('holder', $holder)
            ->update(['expires_at' => now()->addMinutes(self::TTL_MINUTES)]);
    }

    /**
     * Gibt alle Reservierungen eines Holders frei.
     */
    public function release(string $holder): void
    {
        $productIds = ProductReservation::query()
            ->where('holder', $holder)
            ->pluck('product_id')
            ->all();

        if ($productIds === []) {
            return;
        }

        ProductReservation::query()
            ->where('holder', $holder)
            ->delete();

        $this->broadcast($productIds);
    }

    public static function selfOrderHolder(SelfOrder|int $selfOrder): string
    {
        return 'self-order:'.($selfOrder instanceof SelfOrder ? $selfOrder->id : $selfOrder);
    }

    /**
     * Übergibt die Warenkorb-Reservierungen eines Gastes an die
     * eingefrorene Self-Order, damit sie während der Zahlung halten.
     */
    public function transferToSelfOrder(string $holder, SelfOrder $selfOrder): void
    {
        ProductReservation::query()
            ->where('holder', $holder)
            ->update([
                'holder' => self::selfOrderHolder($selfOrder),
                'self_order_id' => $selfOrder->id,
                'expires_at' => now()->addMinutes(self::SELF_ORDER_TTL_MINUTES),
            ]);
    }

    /**
     * Bucht die Mengen eines Warenkorbs endgültig vom Bestand ab und
     * löst die Reservierungen des Holders auf. Muss innerhalb der
     * Order-Transaktion aufgerufen werden.
     *
     * @param  array<int|string, array{id: int, quantity: int}>  $cart
     * @param  bool  $strict  false = nicht werfen, sondern auf 0 klemmen
     *                        (z. B. bereits bezahlte Self-Order)
     */
    public function consume(array $cart, ?string $holder, bool $strict = true): void
    {
        $quantities = [];

        foreach ($cart as $item) {
            $quantity = (int) ($item['quantity'] ?? 0);

            if ($quantity > 0) {
                $productId = (int) $item['id'];
                $quantities[$productId] = ($quantities[$productId] ?? 0) + $quantity;
            }
        }

        if ($quantities === []) {
            return;
        }

        /*
         * Sortiert sperren, um Deadlocks zwischen parallelen
         * Bestellungen zu vermeiden.
         */
        ksort($quantities);

        $products = Product::query()
            ->whereIn('id', array_keys($quantities))
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($quantities as $productId => $quantity) {
            $product = $products->get($productId);

            if (! $product || $product->isUnlimited()) {
                continue;
            }

            $available = (int) $product->available_quantity
                - $this->reservedByOthers($productId, $holder);

            if ($quantity > $available) {
                if ($strict) {
                    throw new InsufficientStockException(
                        'Von '.$product->name.' sind nur noch '
                        .max(0, $available).' Stück verfügbar.'
                    );
                }

                Log::warning('Bestand überbucht, auf 0 geklemmt.', [
                    'product_id' => $productId,
                    'requested' => $quantity,
                    'available' => $available,
                    'holder' => $holder,
                ]);
            }

            $product->available_quantity = max(0, (int) $product->available_quantity - $quantity);
            $product->save();
        }

        if ($holder !== null) {
            ProductReservation::query()
                ->where('holder', $holder)
                ->delete();
        }

        /*
         * Das Live-Signal kommt über den saved-Hook der Produkte.
         */
    }

    /**
     * Bucht eine stornierte Menge zurück in den Bestand.
     */
    public function restock(?int $productId, int $quantity): void
    {
        if ($productId === null || $quantity <= 0) {
            return;
        }

        $product = Product::query()
            ->whereKey($productId)
            ->lockForUpdate()
            ->first();

        if (! $product || $product->isUnlimited()) {
            return;
        }

        $product->available_quantity = (int) $product->available_quantity + $quantity;
        $product->save();
    }

    /**
     * Löscht abgelaufene Reservierungen. Reservierungen von Self-Orders,
     * deren Zahlung bereits läuft oder abgeschlossen ist, bleiben bis
     * zur Übernahme in die Order erhalten.
     */
    public function purgeExpired(): int
    {
        $query = ProductReservation::query()
            ->where('expires_at', '<=', now())
            ->where(function ($query): void {
                $query->whereNull('self_order_id')
                    ->orWhereNotIn('self_order_id', SelfOrder::query()
                        ->whereIn('status', [
                            SelfOrder::STATUS_PAYMENT_PROCESSING,
                            SelfOrder::STATUS_PAID,
                        ])
                        ->select('id'));
            });

        $productIds = (clone $query)->pluck('product_id')->unique()->values()->all();

        if ($productIds === []) {
            return 0;
        }

        $deleted = $query->delete();

        $this->broadcast($productIds);

        return $deleted;
    }

    /**
     * Für einen Holder verfügbare Menge (inkl. seiner eigenen
     * Reservierung), null = unbegrenzt.
     */
    public function availableFor(Product $product, string $holder): ?int
    {
        if ($product->isUnlimited()) {
            return null;
        }

        return max(
            0,
            (int) $product->available_quantity - $this->reservedByOthers($product->id, $holder)
        );
    }

    private function reservedByOthers(int $productId, ?string $holder): int
    {
        return (int) ProductReservation::query()
            ->active()
            ->where('product_id', $productId)
            ->when($holder !== null, fn ($query) => $query->where('holder', '!=', $holder))
            ->sum('quantity');
    }

    /**
     * Noch nicht versendete Produkt-IDs, gesammelt bis zum Commit,
     * damit z. B. ein Bonieren mit mehreren Produkten nur ein
     * einziges Live-Signal erzeugt.
     *
     * @var array<int, int>
     */
    private static array $pendingBroadcast = [];

    /**
     * @param  array<int, int>  $productIds
     */
    public function broadcast(array $productIds): void
    {
        if ($productIds === []) {
            return;
        }

        foreach ($productIds as $productId) {
            self::$pendingBroadcast[(int) $productId] = (int) $productId;
        }

        /*
         * Jeder Commit versendet alles bis dahin Gesammelte; spätere
         * Callbacks derselben Transaktion finden die Liste leer vor.
         */
        DB::afterCommit(function (): void {
            if (self::$pendingBroadcast === []) {
                return;
            }

            $productIds = array_values(self::$pendingBroadcast);
            self::$pendingBroadcast = [];

            try {
                /*
                 * Gequeued: kostet hier nur einen Queue-Eintrag,
                 * der Versand an Reverb läuft im Worker.
                 */
                ProductStockChanged::dispatch($productIds);
            } catch (\Throwable $e) {
                Log::warning('Bestands-Broadcast fehlgeschlagen: '.$e->getMessage());
            }
        });
    }
}
