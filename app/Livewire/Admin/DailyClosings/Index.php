<?php

namespace App\Livewire\Admin\DailyClosings;

use App\Models\DailyClosing;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $year = '';

    public function mount(): void
    {
        if ($this->year === '') {
            $this->year = now()->format('Y');
        }
    }

    public function updatedYear(): void
    {
        $this->resetPage();
    }

    public function getClosingsProperty()
    {
        return DailyClosing::query()
            ->with('closedByUser')
            ->when(
                $this->year !== 'all',
                fn ($query) => $query->whereYear(
                    'business_date',
                    (int) $this->year
                )
            )
            ->latest('business_date')
            ->paginate(25);
    }

    public function getSummaryProperty(): array
    {
        $query = DailyClosing::query()
            ->when(
                $this->year !== 'all',
                fn ($query) => $query->whereYear(
                    'business_date',
                    (int) $this->year
                )
            );

        return [
            'count' => (clone $query)->count(),

            'paid_amount' => round(
                (float) (clone $query)->sum('paid_amount'),
                2
            ),

            'cash_amount' => round(
                (float) (clone $query)->sum('cash_amount'),
                2
            ),

            'card_amount' => round(
                (float) (clone $query)->sum('card_amount'),
                2
            ),

            'cancelled_amount' => round(
                (float) (clone $query)->sum('cancelled_amount'),
                2
            ),
        ];
    }

    public function render()
    {
        $firstYear = DailyClosing::query()
            ->min('business_date');

        $startYear = $firstYear
            ? (int) date('Y', strtotime($firstYear))
            : (int) now()->format('Y');

        $currentYear = (int) now()->format('Y');

        return view(
            'livewire.admin.daily-closings.index',
            [
                'closings' => $this->closings,
                'summary' => $this->summary,
                'years' => range(
                    $currentYear,
                    $startYear
                ),
            ]
        )->layout('components.layouts.app');
    }
}
