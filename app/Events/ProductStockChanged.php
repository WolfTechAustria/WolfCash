<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Live-Signal an alle Kassen: der verfügbare Bestand
 * dieser Produkte hat sich geändert.
 */
class ProductStockChanged implements ShouldBroadcastNow
{
    use Dispatchable;

    /**
     * @param  array<int, int>  $productIds
     */
    public function __construct(public array $productIds)
    {
    }

    public function broadcastOn(): Channel
    {
        return new Channel('stock');
    }

    public function broadcastAs(): string
    {
        return 'stock.changed';
    }
}
