<?php

namespace App\Livewire\Admin\DailySummary;

use App\Models\Order;
use App\Models\OrderItemCancellation;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use App\Models\DailyClosing;
use App\Services\DailyClosingService;
use Illuminate\Validation\ValidationException;
use Throwable;

class Index extends Component
{
    #[Url]
    public string $date = '';

    public ?DailyClosing $dailyClosing = null;

    public function mount(): void
    {
        if ($this->date === '') {
            $this->date = today()->format('Y-m-d');
        }

        $this->loadDailyClosing();
    }

    public function previousDay(): void
    {
        $this->date = $this->selectedDate()
            ->subDay()
            ->format('Y-m-d');

        $this->loadDailyClosing();
    }

    public function nextDay(): void
    {
        $this->date = $this->selectedDate()
            ->addDay()
            ->format('Y-m-d');

        $this->loadDailyClosing();
    }

    public function goToToday(): void
    {
        $this->date = today()->format('Y-m-d');
        $this->loadDailyClosing();

    }

    public function updatedDate(): void
    {
        /*
         * Ungültige Eingaben auf heute zurücksetzen.
         */
        try {
            $this->selectedDate();
        } catch (\Throwable) {
            $this->date = today()->format('Y-m-d');
        }

        $this->loadDailyClosing();
    }

    private function selectedDate(): Carbon
    {
        return Carbon::createFromFormat(
            'Y-m-d',
            $this->date
        )->startOfDay();
    }

    private function ordersQuery(): Builder
    {
        return Order::query()
            ->whereDate(
                'created_at',
                $this->selectedDate()
            );
    }

    private function paymentsQuery(): Builder
    {
        return Payment::query()
            ->whereDate(
                'created_at',
                $this->selectedDate()
            );
    }

    private function cancellationsQuery(): Builder
    {
        return OrderItemCancellation::query()
            ->whereDate(
                'cancelled_at',
                $this->selectedDate()
            );
    }

    public function getSummaryProperty(): array
    {
        /*
         * Bestellungen inklusive Positionen und Zahlungen laden,
         * damit alle Kennzahlen aus derselben Datenbasis entstehen.
         */
        $orders = $this->ordersQuery()
            ->with([
                'items',
                'payments',
            ])
            ->get();

        $payments = $this->paymentsQuery()->get();

        $cancellations = $this->cancellationsQuery()
            ->with('orderItem')
            ->get();

        $grossAmount = $orders->sum(
            fn (Order $order) =>
            $order->items->sum(
                fn ($item) =>
                    (float) $item->price
                    * (int) $item->quantity
            )
        );

        $cancelledAmount = $cancellations->sum(
            fn (OrderItemCancellation $cancellation) =>
                (float) (
                    $cancellation->orderItem?->price
                    ?? 0
                )
                * (int) $cancellation->quantity
        );

        $payableAmount = $orders->sum(
            fn (Order $order) =>
            $order->items->sum(
                fn ($item) =>
                    (float) $item->price
                    * max(
                        0,
                        (int) $item->quantity
                        - (int) $item->cancelled_quantity
                    )
            )
        );

        /*
         * Eingelöste Bons sind bereits an der stationären Kassa als
         * Umsatz verbucht und dürfen hier nicht doppelt zählen.
         */
        $voucherOrderAmount = (float) $orders->sum(
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

        $cashAmount = (float) $payments
            ->where('payment_method', 'cash')
            ->sum('amount');

        $cardAmount = (float) $payments
            ->where('payment_method', 'card')
            ->sum('amount');

        return [
            'orders_total' => $orders->count(),

            'orders_open' => $orders
                ->where('status', Order::STATUS_OPEN)
                ->count(),

            'orders_paid' => $orders
                ->where('status', Order::STATUS_PAID)
                ->count(),

            'orders_cancelled' => $orders
                ->where('status', Order::STATUS_CANCELLED)
                ->count(),

            'gross_amount' => round(
                $grossAmount,
                2
            ),

            'cancelled_amount' => round(
                $cancelledAmount,
                2
            ),

            'payable_amount' => round(
                $payableAmount,
                2
            ),

            'paid_amount' => round(
                $paidAmount,
                2
            ),

            'cash_amount' => round(
                $cashAmount,
                2
            ),

            'card_amount' => round(
                $cardAmount,
                2
            ),

            'voucher_amount' => round(
                $voucherAmount,
                2
            ),

            'open_amount' => max(
                0,
                round(
                    $payableAmount - $paidAmount,
                    2
                )
            ),

            'payments_count' => $payments->count(),

            'cancellations_count' =>
                $cancellations->count(),

            'cancelled_quantity' =>
                $cancellations->sum('quantity'),
        ];
    }

    public function getOpenOrdersProperty()
    {
        return $this->ordersQuery()
            ->with([
                'table',
                'items',
                'payments',
            ])
            ->where('status', Order::STATUS_OPEN)
            ->latest('created_at')
            ->get();
    }

    public function getPaymentsProperty()
    {
        return $this->paymentsQuery()
            ->with([
                'order.table',
            ])
            ->latest('created_at')
            ->get();
    }

    public function closeDay(
        DailyClosingService $dailyClosingService
    ): void {
        try {
            $closing = $dailyClosingService->close(
                businessDate: $this->selectedDate(),
                userId: auth()->id(),
            );

            $this->dailyClosing = $closing->load(
                'closedByUser'
            );

            session()->flash(
                'success',
                'Der Geschäftstag wurde erfolgreich abgeschlossen.'
            );
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'dailyClosing' => $exception->getMessage(),
            ]);
        }
    }

    private function loadDailyClosing(): void
    {
        $this->dailyClosing = DailyClosing::query()
            ->with('closedByUser')
            ->whereDate(
                'business_date',
                $this->selectedDate()
            )
            ->first();
    }

    public function render()
    {
        return view(
            'livewire.admin.daily-summary.index',
            [
                'summary' => $this->summary,
                'openOrders' => $this->openOrders,
                'payments' => $this->payments,
                'selectedDate' => $this->selectedDate(),
            ]
        )->layout('components.layouts.app');
    }
}
