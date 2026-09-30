<?php

namespace App\Livewire\Admin\Dashboard;

use App\Enums\DeviceStatus;
use App\Models\DailyClosing;
use App\Models\Device;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemCancellation;
use App\Models\Payment;
use App\Models\PrintJob;
use App\Models\Table;
use App\Services\ServerMetrics;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Index extends Component
{
    /**
     * Geräte gelten als online, wenn sie sich innerhalb dieses
     * Zeitraums gemeldet haben (Heartbeat/letzter Request).
     */
    private const DEVICE_ONLINE_MINUTES = 5;

    /**
     * Umsatzrelevante Zahlungen. Eingelöste Bons sind bereits an der
     * stationären Kassa verbucht und zählen wie in der Tagesübersicht
     * nicht doppelt.
     */
    private function revenuePayments(): Builder
    {
        return Payment::query()
            ->where('payment_method', '!=', Payment::VOUCHER);
    }

    private function kpis(): array
    {
        $now = now();
        $today = today();
        $yesterday = today()->subDay();

        $revenueToday = (float) $this->revenuePayments()
            ->whereDate('created_at', $today)
            ->sum('amount');

        $paymentsToday = $this->revenuePayments()
            ->whereDate('created_at', $today)
            ->count();

        /*
         * Vergleich mit gestern bis zur selben Uhrzeit, damit der
         * Trend schon während des laufenden Betriebs aussagekräftig ist.
         */
        $revenueYesterdaySameTime = (float) $this->revenuePayments()
            ->whereBetween('created_at', [
                $yesterday,
                $yesterday->copy()->setTimeFrom($now),
            ])
            ->sum('amount');

        $openOrders = Order::query()
            ->where('status', Order::STATUS_OPEN);

        $openAmount = (float) OrderItem::query()
            ->whereIn('order_id', (clone $openOrders)->select('id'))
            ->whereNull('paid_at')
            ->selectRaw('COALESCE(SUM((quantity - cancelled_quantity) * price), 0) AS open_amount')
            ->value('open_amount');

        $tables = Table::query()->where('is_stationary', false);

        $cancellationsToday = OrderItemCancellation::query()
            ->with('orderItem:id,price')
            ->whereDate('cancelled_at', $today)
            ->get();

        return [
            'revenue_today' => round($revenueToday, 2),
            'revenue_yesterday_same_time' => round($revenueYesterdaySameTime, 2),
            'revenue_trend' => $revenueYesterdaySameTime > 0
                ? round(($revenueToday - $revenueYesterdaySameTime) / $revenueYesterdaySameTime * 100)
                : null,
            'payments_today' => $paymentsToday,
            'average_receipt' => $paymentsToday > 0
                ? round($revenueToday / $paymentsToday, 2)
                : 0,
            'orders_today' => Order::query()->whereDate('created_at', $today)->count(),
            'orders_open' => (clone $openOrders)->count(),
            'open_amount' => round(max(0, $openAmount), 2),
            'tables_total' => (clone $tables)->count(),
            'tables_occupied' => (clone $tables)->whereHas('openOrder')->count(),
            'cancellations_count' => $cancellationsToday->count(),
            'cancellations_amount' => round($cancellationsToday->sum(
                fn (OrderItemCancellation $cancellation) =>
                    (float) ($cancellation->orderItem?->price ?? 0) * (int) $cancellation->quantity
            ), 2),
        ];
    }

    /**
     * Umsatz je Stunde des heutigen Tages, von der ersten Stunde mit
     * Umsatz (spätestens 8 Uhr) bis zur aktuellen Stunde.
     */
    private function hourlyRevenue(): array
    {
        $byHour = $this->revenuePayments()
            ->whereDate('created_at', today())
            ->get(['amount', 'created_at'])
            ->groupBy(fn (Payment $payment) => (int) $payment->created_at->format('G'))
            ->map(fn ($payments) => round((float) $payments->sum('amount'), 2));

        $currentHour = (int) now()->format('G');
        $firstHour = min(8, $byHour->keys()->min() ?? 8, $currentHour);

        $hours = [];

        for ($hour = $firstHour; $hour <= $currentHour; $hour++) {
            $hours[] = [
                'label' => sprintf('%02d', $hour),
                'value' => $byHour->get($hour, 0.0),
                'current' => $hour === $currentHour,
            ];
        }

        return $hours;
    }

    private function weeklyRevenue(): array
    {
        $start = today()->subDays(6);

        $byDay = $this->revenuePayments()
            ->where('created_at', '>=', $start)
            ->get(['amount', 'created_at'])
            ->groupBy(fn (Payment $payment) => $payment->created_at->format('Y-m-d'))
            ->map(fn ($payments) => round((float) $payments->sum('amount'), 2));

        $days = [];

        for ($date = $start->copy(); $date->lte(today()); $date->addDay()) {
            $days[] = [
                'label' => $date->locale('de')->minDayName,
                'date' => $date->format('d.m.'),
                'value' => $byDay->get($date->format('Y-m-d'), 0.0),
                'current' => $date->isToday(),
            ];
        }

        return $days;
    }

    private function paymentMethods(): array
    {
        return Payment::query()
            ->whereDate('created_at', today())
            ->select('payment_method', DB::raw('SUM(amount) AS total'), DB::raw('COUNT(*) AS count'))
            ->groupBy('payment_method')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'label' => Payment::methodLabel($row->payment_method),
                'total' => round((float) $row->total, 2),
                'count' => (int) $row->count,
                'is_voucher' => $row->payment_method === Payment::VOUCHER,
            ])
            ->all();
    }

    private function topProducts(): array
    {
        return OrderItem::query()
            ->whereDate('order_items.created_at', today())
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->select(
                'products.name',
                DB::raw('SUM(order_items.quantity - order_items.cancelled_quantity) AS quantity'),
                DB::raw('SUM((order_items.quantity - order_items.cancelled_quantity) * order_items.price) AS revenue'),
            )
            ->groupBy('products.id', 'products.name')
            ->havingRaw('SUM(order_items.quantity - order_items.cancelled_quantity) > 0')
            ->orderByDesc('quantity')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'quantity' => (int) $row->quantity,
                'revenue' => round((float) $row->revenue, 2),
            ])
            ->all();
    }

    private function operations(): array
    {
        $approvedDevices = Device::query()->where('status', DeviceStatus::Approved);

        return [
            'devices_approved' => (clone $approvedDevices)->count(),
            'devices_online' => (clone $approvedDevices)
                ->where('last_seen_at', '>=', now()->subMinutes(self::DEVICE_ONLINE_MINUTES))
                ->count(),
            'devices_pending' => Device::query()->where('status', DeviceStatus::Pending)->count(),
            'print_jobs_waiting' => PrintJob::query()
                ->whereIn('status', [PrintJob::STATUS_PENDING, PrintJob::STATUS_PRINTING])
                ->count(),
            'print_jobs_failed_today' => PrintJob::query()
                ->where('status', PrintJob::STATUS_FAILED)
                ->whereDate('created_at', today())
                ->count(),
            'daily_closing_today' => DailyClosing::query()
                ->whereDate('business_date', today())
                ->first(),
            'last_closing' => DailyClosing::query()
                ->latest('business_date')
                ->first(),
        ];
    }

    public function render(ServerMetrics $serverMetrics)
    {
        return view('livewire.admin.dashboard.index', [
            'kpis' => $this->kpis(),
            'hourlyRevenue' => $this->hourlyRevenue(),
            'weeklyRevenue' => $this->weeklyRevenue(),
            'paymentMethods' => $this->paymentMethods(),
            'topProducts' => $this->topProducts(),
            'operations' => $this->operations(),
            'server' => $serverMetrics->collect(),
            'updatedAt' => now(),
        ])->layout('components.layouts.app');
    }
}
