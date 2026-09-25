<?php

namespace App\Livewire\Admin\ProductReports;

use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProductCategory;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;

class Index extends Component
{
    #[Url]
    public string $period = 'today';

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    #[Url]
    public ?int $categoryId = null;

    #[Url]
    public string $group = 'all';

    #[Url]
    public string $sort = 'revenue';

    #[Url]
    public string $direction = 'desc';

    #[Url]
    public string $search = '';

    public function mount(): void
    {
        if ($this->dateFrom === '') {
            $this->dateFrom = today()->format('Y-m-d');
        }

        if ($this->dateTo === '') {
            $this->dateTo = today()->format('Y-m-d');
        }
    }

    public function updatedPeriod(): void
    {
        $this->applyPeriodDates();
    }

    public function resetFilters(): void
    {
        $this->period = 'today';
        $this->dateFrom = today()->format('Y-m-d');
        $this->dateTo = today()->format('Y-m-d');
        $this->categoryId = null;
        $this->group = 'all';
        $this->sort = 'revenue';
        $this->direction = 'desc';
        $this->search = '';
    }

    public function sortBy(string $column): void
    {
        $allowedColumns = [
            'name',
            'quantity',
            'revenue',
            'cancelled_quantity',
            'cancelled_amount',
        ];

        if (! in_array($column, $allowedColumns, true)) {
            return;
        }

        if ($this->sort === $column) {
            $this->direction = $this->direction === 'asc'
                ? 'desc'
                : 'asc';

            return;
        }

        $this->sort = $column;
        $this->direction = $column === 'name'
            ? 'asc'
            : 'desc';
    }

    private function applyPeriodDates(): void
    {
        [$from, $to] = match ($this->period) {
            'yesterday' => [
                today()->subDay(),
                today()->subDay(),
            ],

            'week' => [
                now()->startOfWeek(),
                now()->endOfWeek(),
            ],

            'month' => [
                now()->startOfMonth(),
                now()->endOfMonth(),
            ],

            'year' => [
                now()->startOfYear(),
                now()->endOfYear(),
            ],

            'custom' => [
                $this->parseDate($this->dateFrom),
                $this->parseDate($this->dateTo),
            ],

            default => [
                today(),
                today(),
            ],
        };

        $this->dateFrom = $from->format('Y-m-d');
        $this->dateTo = $to->format('Y-m-d');
    }

    private function parseDate(string $date): Carbon
    {
        try {
            return Carbon::createFromFormat(
                'Y-m-d',
                $date
            )->startOfDay();
        } catch (\Throwable) {
            return today();
        }
    }

    private function startDate(): Carbon
    {
        return $this->parseDate($this->dateFrom)
            ->startOfDay();
    }

    private function endDate(): Carbon
    {
        return $this->parseDate($this->dateTo)
            ->endOfDay();
    }

    private function orderItemsQuery(): Builder
    {
        $from = $this->startDate();
        $to = $this->endDate();

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        return OrderItem::query()
            ->with([
                'product.category',
                'order',
            ])
            ->whereHas(
                'order',
                fn (Builder $query) => $query->whereBetween(
                    'created_at',
                    [$from, $to]
                )
            )
            /*
             * Per Bon eingelöste Positionen wurden bereits an der
             * stationären Kassa verkauft und zählen dort.
             */
            ->whereDoesntHave(
                'payment',
                fn (Builder $query) => $query->where(
                    'payment_method',
                    Payment::VOUCHER
                )
            )
            ->when(
                $this->categoryId,
                fn (Builder $query) => $query->whereHas(
                    'product',
                    fn (Builder $productQuery) =>
                    $productQuery->where(
                        'product_category_id',
                        $this->categoryId
                    )
                )
            )
            ->when(
                $this->group !== 'all',
                fn (Builder $query) => $query->whereHas(
                    'product.category',
                    fn (Builder $categoryQuery) =>
                    $categoryQuery->where(
                        'group',
                        $this->group
                    )
                )
            )
            ->when(
                trim($this->search) !== '',
                function (Builder $query): void {
                    $search = '%'.trim($this->search).'%';

                    $query->whereHas(
                        'product',
                        fn (Builder $productQuery) =>
                        $productQuery->where(
                            'name',
                            'ilike',
                            $search
                        )
                    );
                }
            );
    }

    public function getRowsProperty(): Collection
    {
        $rows = $this->orderItemsQuery()
            ->get()
            ->groupBy('product_id')
            ->map(function (Collection $items): array {
                $firstItem = $items->first();
                $product = $firstItem?->product;

                $orderedQuantity = $items->sum(
                    fn (OrderItem $item) =>
                    (int) $item->quantity
                );

                $cancelledQuantity = $items->sum(
                    fn (OrderItem $item) =>
                    (int) $item->cancelled_quantity
                );

                $soldQuantity = max(
                    0,
                    $orderedQuantity - $cancelledQuantity
                );

                $grossAmount = $items->sum(
                    fn (OrderItem $item) =>
                        (float) $item->price
                        * (int) $item->quantity
                );

                $cancelledAmount = $items->sum(
                    fn (OrderItem $item) =>
                        (float) $item->price
                        * (int) $item->cancelled_quantity
                );

                $revenue = max(
                    0,
                    $grossAmount - $cancelledAmount
                );

                return [
                    'product_id' => $product?->id,
                    'name' => $product?->name
                        ?? 'Gelöschtes Produkt',

                    'category' => $product?->category?->name
                        ?? 'Ohne Kategorie',

                    'group' => $product?->category?->group
                        ?? '–',

                    'ordered_quantity' => $orderedQuantity,
                    'cancelled_quantity' => $cancelledQuantity,
                    'quantity' => $soldQuantity,

                    'gross_amount' => round(
                        $grossAmount,
                        2
                    ),

                    'cancelled_amount' => round(
                        $cancelledAmount,
                        2
                    ),

                    'revenue' => round(
                        $revenue,
                        2
                    ),

                    'average_price' => $soldQuantity > 0
                        ? round(
                            $revenue / $soldQuantity,
                            2
                        )
                        : 0,
                ];
            })
            ->values();

        return $rows->sortBy(
            fn (array $row) => $row[$this->sort]
                ?? null,
            SORT_REGULAR,
            $this->direction === 'desc'
        )->values();
    }

    public function getSummaryProperty(): array
    {
        $rows = $this->rows;

        return [
            'products' => $rows
                ->where('quantity', '>', 0)
                ->count(),

            'ordered_quantity' => $rows
                ->sum('ordered_quantity'),

            'quantity' => $rows
                ->sum('quantity'),

            'cancelled_quantity' => $rows
                ->sum('cancelled_quantity'),

            'gross_amount' => round(
                (float) $rows->sum('gross_amount'),
                2
            ),

            'cancelled_amount' => round(
                (float) $rows->sum('cancelled_amount'),
                2
            ),

            'revenue' => round(
                (float) $rows->sum('revenue'),
                2
            ),
        ];
    }

    public function render()
    {
        return view(
            'livewire.admin.product-reports.index',
            [
                'rows' => $this->rows,
                'summary' => $this->summary,

                'categories' => ProductCategory::query()
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get(),

                'selectedFrom' => $this->startDate(),
                'selectedTo' => $this->endDate(),
            ]
        )->layout('components.layouts.app');
    }
}
