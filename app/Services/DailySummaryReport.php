<?php

namespace App\Services;

use App\Models\Device;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemCancellation;
use App\Models\Payment;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Kennzahlen der Tagesübersicht, optional auf ein Gerät eingeschränkt.
 * Wird von der Admin-Seite und vom PDF-Export gemeinsam genutzt, damit
 * beide dieselben Zahlen zeigen.
 *
 * Gerätezuordnung: Zahlungen zählen für das Gerät, mit dem kassiert
 * wurde (payments.device_id), Positionen und Stornos für das Gerät,
 * mit dem boniert wurde (order_items.device_id).
 */
class DailySummaryReport
{
    /**
     * Filterwert für Zahlungen/Positionen ohne Gerät
     * (z. B. Self-Order online oder Admin).
     */
    public const NO_DEVICE = 'none';

    /**
     * @return array{
     *     summary: array<string, int|float>,
     *     openOrders: Collection<int, Order>,
     *     payments: Collection<int, Payment>,
     *     devices: Collection<int, array<string, mixed>>,
     *     deviceLabel: ?string,
     * }
     */
    public function build(CarbonInterface $date, string $device = ''): array
    {
        $orders = Order::query()
            ->whereDate('created_at', $date)
            ->with(['table', 'items.payment', 'payments'])
            ->get();

        $payments = Payment::query()
            ->whereDate('created_at', $date)
            ->with(['order.table', 'device'])
            ->latest('created_at')
            ->get();

        $cancellations = OrderItemCancellation::query()
            ->whereDate('cancelled_at', $date)
            ->with('orderItem')
            ->get();

        $devices = $this->deviceBreakdown($orders, $payments);

        if ($device === '') {
            return [
                'summary' => $this->summary($orders, $payments, $cancellations),
                'openOrders' => $this->openOrders($orders),
                'payments' => $payments,
                'devices' => $devices,
                'deviceLabel' => null,
            ];
        }

        $deviceId = $device === self::NO_DEVICE ? null : (int) $device;

        $matches = fn (?int $id): bool => $deviceId === null
            ? $id === null
            : $id === $deviceId;

        /*
         * Nur Bestellungen mit vom Gerät bonierten Positionen, und an
         * diesen nur die eigenen Positionen.
         */
        $filteredOrders = $orders
            ->map(function (Order $order) use ($matches): Order {
                $order->setRelation(
                    'items',
                    $order->items->filter(fn (OrderItem $item) => $matches($item->device_id))->values()
                );

                return $order;
            })
            ->filter(fn (Order $order) => $order->items->isNotEmpty())
            ->values();

        $filteredPayments = $payments
            ->filter(fn (Payment $payment) => $matches($payment->device_id))
            ->values();

        $filteredCancellations = $cancellations
            ->filter(fn (OrderItemCancellation $c) => $matches($c->orderItem?->device_id))
            ->values();

        return [
            'summary' => $this->summary(
                $filteredOrders,
                $filteredPayments,
                $filteredCancellations,
                itemBased: true,
            ),
            'openOrders' => $this->openOrders($filteredOrders),
            'payments' => $filteredPayments,
            'devices' => $devices,
            'deviceLabel' => $deviceId === null
                ? 'Ohne Gerät'
                : (Device::query()->whereKey($deviceId)->value('name') ?? 'Gerät #'.$deviceId),
        ];
    }

    /**
     * @param Collection<int, Order> $orders
     * @param Collection<int, Payment> $payments
     * @param Collection<int, OrderItemCancellation> $cancellations
     * @param bool $itemBased beim Gerätefilter Bons und offenen Betrag
     *        aus den eigenen Positionen rechnen, da Zahlungen eines
     *        Tisches von anderen Geräten kassiert worden sein können.
     * @return array<string, int|float>
     */
    private function summary(
        Collection $orders,
        Collection $payments,
        Collection $cancellations,
        bool $itemBased = false,
    ): array {
        $items = $orders->flatMap(fn (Order $order) => $order->items);

        $grossAmount = $items->sum(
            fn (OrderItem $item) => (float) $item->price * (int) $item->quantity
        );

        $payableAmount = $items->sum(
            fn (OrderItem $item) => (float) $item->price * $this->openQuantity($item)
        );

        $cancelledAmount = $cancellations->sum(
            fn (OrderItemCancellation $cancellation) =>
                (float) ($cancellation->orderItem?->price ?? 0) * (int) $cancellation->quantity
        );

        /*
         * Eingelöste Bons sind bereits an der stationären Kassa als
         * Umsatz verbucht und dürfen hier nicht doppelt zählen.
         */
        $voucherOrderAmount = $itemBased
            ? (float) $items
                ->filter(fn (OrderItem $item) => $item->payment?->payment_method === Payment::VOUCHER)
                ->sum(fn (OrderItem $item) => (float) $item->price * $this->openQuantity($item))
            : (float) $orders->sum(
                fn (Order $order) => $order->payments
                    ->where('payment_method', Payment::VOUCHER)
                    ->sum('amount')
            );

        $voucherAmount = (float) $payments
            ->where('payment_method', Payment::VOUCHER)
            ->sum('amount');

        $grossAmount -= $voucherOrderAmount;
        $payableAmount -= $voucherOrderAmount;

        $paidAmount = (float) $payments->sum('amount') - $voucherAmount;

        $openAmount = $itemBased
            ? $items
                ->whereNull('paid_at')
                ->sum(fn (OrderItem $item) => (float) $item->price * $this->openQuantity($item))
            : $payableAmount - $paidAmount;

        return [
            'orders_total' => $orders->count(),
            'orders_open' => $orders->where('status', Order::STATUS_OPEN)->count(),
            'orders_paid' => $orders->where('status', Order::STATUS_PAID)->count(),
            'orders_cancelled' => $orders->where('status', Order::STATUS_CANCELLED)->count(),
            'gross_amount' => round($grossAmount, 2),
            'cancelled_amount' => round($cancelledAmount, 2),
            'payable_amount' => round($payableAmount, 2),
            'paid_amount' => round($paidAmount, 2),
            'cash_amount' => round((float) $payments->where('payment_method', Payment::CASH)->sum('amount'), 2),
            'card_amount' => round((float) $payments->where('payment_method', Payment::CARD)->sum('amount'), 2),
            'voucher_amount' => round($voucherAmount, 2),
            'open_amount' => max(0, round($openAmount, 2)),
            'payments_count' => $payments->count(),
            'cancellations_count' => $cancellations->count(),
            'cancelled_quantity' => (int) $cancellations->sum('quantity'),
        ];
    }

    /**
     * Ein Eintrag pro Gerät, das an diesem Tag boniert oder kassiert hat.
     *
     * @param Collection<int, Order> $orders
     * @param Collection<int, Payment> $payments
     * @return Collection<int, array<string, mixed>>
     */
    private function deviceBreakdown(Collection $orders, Collection $payments): Collection
    {
        $items = $orders->flatMap(fn (Order $order) => $order->items);

        $keys = $items->pluck('device_id')
            ->merge($payments->pluck('device_id'))
            ->map(fn ($id) => $id === null ? self::NO_DEVICE : (string) $id)
            ->unique();

        $names = Device::query()
            ->whereKey($keys->reject(self::NO_DEVICE)->all())
            ->pluck('name', 'id');

        return $keys
            ->map(function (string $key) use ($items, $payments, $names): array {
                $deviceId = $key === self::NO_DEVICE ? null : (int) $key;

                $deviceItems = $items->filter(fn (OrderItem $item) => $item->device_id === $deviceId);
                $devicePayments = $payments->filter(fn (Payment $payment) => $payment->device_id === $deviceId);

                $voucher = (float) $devicePayments->where('payment_method', Payment::VOUCHER)->sum('amount');

                return [
                    'key' => $key,
                    'name' => $deviceId === null
                        ? 'Ohne Gerät'
                        : ($names[$deviceId] ?? 'Gerät #'.$deviceId),
                    'booked_amount' => round($deviceItems
                        ->reject(fn (OrderItem $item) => $item->payment?->payment_method === Payment::VOUCHER)
                        ->sum(fn (OrderItem $item) => (float) $item->price * $this->openQuantity($item)), 2),
                    'paid_amount' => round((float) $devicePayments->sum('amount') - $voucher, 2),
                    'cash_amount' => round((float) $devicePayments->where('payment_method', Payment::CASH)->sum('amount'), 2),
                    'card_amount' => round((float) $devicePayments->where('payment_method', Payment::CARD)->sum('amount'), 2),
                    'voucher_amount' => round($voucher, 2),
                    'payments_count' => $devicePayments->count(),
                ];
            })
            ->sortBy([
                fn (array $a, array $b) => ($a['key'] === self::NO_DEVICE) <=> ($b['key'] === self::NO_DEVICE),
                fn (array $a, array $b) => strnatcasecmp($a['name'], $b['name']),
            ])
            ->values();
    }

    /**
     * @param Collection<int, Order> $orders
     * @return Collection<int, Order>
     */
    private function openOrders(Collection $orders): Collection
    {
        return $orders
            ->where('status', Order::STATUS_OPEN)
            ->sortByDesc('created_at')
            ->values();
    }

    private function openQuantity(OrderItem $item): int
    {
        return max(0, (int) $item->quantity - (int) $item->cancelled_quantity);
    }
}
