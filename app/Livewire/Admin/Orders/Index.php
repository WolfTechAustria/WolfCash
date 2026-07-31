<?php

namespace App\Livewire\Admin\Orders;

use App\Models\Order;
use App\Models\Table;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $period = 'today';

    #[Url]
    public string $status = 'all';

    #[Url]
    public ?int $tableId = null;

    public function updatedPeriod(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedTableId(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->period = 'today';
        $this->status = 'all';
        $this->tableId = null;

        $this->resetPage();
    }

    public function getOrdersProperty(): LengthAwarePaginator
    {
        return Order::query()
            ->with([
                'table',
                'items.product',
                'payments',
            ])
            ->withCount('items')
            ->when(
                $this->period === 'today',
                fn ($query) => $query->whereDate(
                    'created_at',
                    today()
                )
            )
            ->when(
                $this->period === 'yesterday',
                fn ($query) => $query->whereDate(
                    'created_at',
                    today()->subDay()
                )
            )
            ->when(
                $this->period === 'week',
                fn ($query) => $query->whereBetween(
                    'created_at',
                    [
                        now()->startOfWeek(),
                        now()->endOfWeek(),
                    ]
                )
            )
            ->when(
                $this->period === 'month',
                fn ($query) => $query->whereBetween(
                    'created_at',
                    [
                        now()->startOfMonth(),
                        now()->endOfMonth(),
                    ]
                )
            )
            ->when(
                $this->status !== 'all',
                fn ($query) => $query->where(
                    'status',
                    $this->status
                )
            )
            ->when(
                $this->tableId,
                fn ($query) => $query->where(
                    'table_id',
                    $this->tableId
                )
            )
            ->latest('created_at')
            ->paginate(25);
    }

    public function getSummaryProperty(): array
    {
        $orders = collect($this->orders->items());

        return [
            'orders' => $orders->count(),

            'gross' => round(
                $orders->sum(
                    fn (Order $order) =>
                    $order->items->sum(
                        fn ($item) =>
                            (float) $item->price
                            * (int) $item->quantity
                    )
                ),
                2
            ),

            'cancelled' => round(
                $orders->sum(
                    fn (Order $order) =>
                    $order->items->sum(
                        fn ($item) =>
                            (float) $item->price
                            * (int) $item->cancelled_quantity
                    )
                ),
                2
            ),

            'paid' => round(
                $orders->sum(
                    fn (Order $order) =>
                    $order->payments->sum('amount')
                ),
                2
            ),
        ];
    }

    public function render()
    {
        return view('livewire.admin.orders.index', [
            'tables' => Table::query()
                ->orderBy('number')
                ->get(),

            'orders' => $this->orders,
            'summary' => $this->summary,
        ])->layout('components.layouts.app');
    }
}
