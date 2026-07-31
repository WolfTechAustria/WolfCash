<?php

namespace App\Livewire\Admin\Cancellations;

use App\Models\OrderItemCancellation;
use App\Models\Product;
use App\Models\Table;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $period = 'today';

    #[Url]
    public ?int $tableId = null;

    #[Url]
    public ?int $productId = null;

    #[Url]
    public ?int $userId = null;

    #[Url]
    public string $search = '';

    public function updatedPeriod(): void
    {
        $this->resetPage();
    }

    public function updatedTableId(): void
    {
        $this->resetPage();
    }

    public function updatedProductId(): void
    {
        $this->resetPage();
    }

    public function updatedUserId(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->period = 'today';
        $this->tableId = null;
        $this->productId = null;
        $this->userId = null;
        $this->search = '';

        $this->resetPage();
    }

    private function cancellationQuery(): Builder
    {
        return OrderItemCancellation::query()
            ->with([
                'orderItem.product',
                'orderItem.order.table',
                'cancelledByUser',
            ])
            ->when(
                $this->period === 'today',
                fn (Builder $query) => $query->whereDate(
                    'cancelled_at',
                    today()
                )
            )
            ->when(
                $this->period === 'yesterday',
                fn (Builder $query) => $query->whereDate(
                    'cancelled_at',
                    today()->subDay()
                )
            )
            ->when(
                $this->period === 'week',
                fn (Builder $query) => $query->whereBetween(
                    'cancelled_at',
                    [
                        now()->startOfWeek(),
                        now()->endOfWeek(),
                    ]
                )
            )
            ->when(
                $this->period === 'month',
                fn (Builder $query) => $query->whereBetween(
                    'cancelled_at',
                    [
                        now()->startOfMonth(),
                        now()->endOfMonth(),
                    ]
                )
            )
            ->when(
                $this->tableId,
                fn (Builder $query) => $query->whereHas(
                    'orderItem.order',
                    fn (Builder $orderQuery) => $orderQuery->where(
                        'table_id',
                        $this->tableId
                    )
                )
            )
            ->when(
                $this->productId,
                fn (Builder $query) => $query->whereHas(
                    'orderItem',
                    fn (Builder $itemQuery) => $itemQuery->where(
                        'product_id',
                        $this->productId
                    )
                )
            )
            ->when(
                $this->userId,
                fn (Builder $query) => $query->where(
                    'cancelled_by',
                    $this->userId
                )
            )
            ->when(
                trim($this->search) !== '',
                function (Builder $query): void {
                    $search = '%'.trim($this->search).'%';

                    $query->where(function (Builder $searchQuery) use ($search): void {
                        $searchQuery
                            ->where('reason', 'ilike', $search)
                            ->orWhereHas(
                                'orderItem.product',
                                fn (Builder $productQuery) =>
                                $productQuery->where(
                                    'name',
                                    'ilike',
                                    $search
                                )
                            );
                    });
                }
            );
    }

    public function getCancellationsProperty(): LengthAwarePaginator
    {
        return $this->cancellationQuery()
            ->latest('cancelled_at')
            ->paginate(25);
    }

    public function getSummaryProperty(): array
    {
        /*
         * Die Kennzahlen beziehen sich auf alle aktuell
         * gefilterten Stornos, nicht nur auf die sichtbare Seite.
         */
        $cancellations = $this->cancellationQuery()->get();

        return [
            'count' => $cancellations->count(),

            'quantity' => $cancellations->sum(
                fn (OrderItemCancellation $cancellation) =>
                (int) $cancellation->quantity
            ),

            'amount' => round(
                $cancellations->sum(
                    fn (OrderItemCancellation $cancellation) =>
                        (float) (
                            $cancellation->orderItem?->price ?? 0
                        )
                        * (int) $cancellation->quantity
                ),
                2
            ),
        ];
    }

    public function render()
    {
        return view('livewire.admin.cancellations.index', [
            'cancellations' => $this->cancellations,
            'summary' => $this->summary,

            'tables' => Table::query()
                ->orderBy('number')
                ->get(),

            'products' => Product::query()
                ->orderBy('name')
                ->get(),

            'users' => User::query()
                ->orderBy('name')
                ->get(),
        ])->layout('components.layouts.app');
    }
}
