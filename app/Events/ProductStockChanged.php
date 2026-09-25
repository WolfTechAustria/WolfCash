<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Live-Signal an alle Kassen: der verfügbare Bestand
 * dieser Produkte hat sich geändert.
 *
 * Bewusst gequeued (ShouldBroadcast statt ShouldBroadcastNow):
 * Der Versand an Reverb darf niemals eine Kassenaktion ausbremsen,
 * auch nicht, wenn Reverb gerade nicht erreichbar ist.
 */
class ProductStockChanged implements ShouldBroadcast
{
    use Dispatchable;

    /*
     * Ein veraltetes Live-Signal nicht wiederholen, das Polling
     * gleicht ohnehin nach spätestens 10 s ab.
     */
    public int $tries = 1;

    /**
     * @param  array<int, int>  $productIds
     */
    public function __construct(public array $productIds)
    {
    }

    /*
     * Eigene Queue, damit ein Worker für Live-Signale niemals
     * Druckjobs mitverarbeitet (und umgekehrt).
     */
    public function broadcastQueue(): string
    {
        return 'broadcasts';
    }

    public function broadcastOn(): Channel
    {
        return new Channel('stock');
    }

    public function broadcastAs(): string
    {
        return 'stock.changed';
    }

    /**
     * @return array{productIds: array<int, int>}
     */
    public function broadcastWith(): array
    {
        return ['productIds' => $this->productIds];
    }
}
