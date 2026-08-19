<?php

namespace App\Livewire\SelfOrder;

use App\Models\SelfOrder;
use Livewire\Component;

class PaymentStatus extends Component
{
    public SelfOrder $selfOrder;

    public function mount(
        SelfOrder $selfOrder
    ): void {
        $this->selfOrder = $selfOrder;
    }

    public function refreshStatus(): void
    {
        $this->selfOrder->refresh();
    }

    public function render()
    {
        return view(
            'livewire.self-order.payment-status'
        )->layout(
            'components.layouts.self-order'
        );
    }
}
