<?php

namespace App\Livewire\Admin\Orders;

use App\Models\Order;
use App\Models\PrintJob;
use App\Models\PrintOutput;
use Livewire\Component;

class Show extends Component
{
    public Order $order;

    public function mount(Order $order): void
    {
        $this->order = $order;

        $this->loadOrder();
    }

    public function loadOrder(): void
    {
        $this->order->load([
            'table',
            'items.product',
            'items.cancellations.cancelledByUser',
            'payments',
        ]);
    }

    public function getGrossAmountProperty(): float
    {
        return round(
            $this->order->items->sum(
                fn ($item) =>
                    (float) $item->price
                    * (int) $item->quantity
            ),
            2
        );
    }

    public function getCancelledAmountProperty(): float
    {
        return round(
            $this->order->items->sum(
                fn ($item) =>
                    (float) $item->price
                    * (int) $item->cancelled_quantity
            ),
            2
        );
    }

    public function getPayableAmountProperty(): float
    {
        return round(
            $this->grossAmount
            - $this->cancelledAmount,
            2
        );
    }

    public function getPaidAmountProperty(): float
    {
        return round(
            (float) $this->order->payments->sum('amount'),
            2
        );
    }

    public function getOpenAmountProperty(): float
    {
        return max(
            0,
            round(
                $this->payableAmount
                - $this->paidAmount,
                2
            )
        );
    }

    public function getPrintJobsProperty()
    {
        return PrintJob::query()
            ->with([
                'printer',
                'productionStation',
            ])
            ->where('order_id', $this->order->id)
            ->latest('created_at')
            ->get();
    }

    public function getPrintOutputsProperty()
    {
        return PrintOutput::query()
            ->with([
                'printer',
                'orderItem.product',
            ])
            ->whereHas(
                'printJob',
                fn ($query) =>
                $query->where(
                    'order_id',
                    $this->order->id
                )
            )
            ->latest('created_at')
            ->get();
    }

    public function render()
    {
        return view('livewire.admin.orders.show', [
            'printJobs' => $this->printJobs,
            'printOutputs' => $this->printOutputs,
        ])->layout('components.layouts.app');
    }
}
