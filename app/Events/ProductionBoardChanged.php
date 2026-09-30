<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Live-Signal an alle Küchenmonitore: an den offenen Bons
 * dieser Produktionsstationen hat sich etwas geändert
 * (neuer Bon, Position fertig/wieder offen, Bon abgeschlossen).
 *
 * Bewusst gequeued wie ProductStockChanged: Der Versand an Reverb
 * darf niemals das Bonieren oder die Küche ausbremsen.
 */
class ProductionBoardChanged implements ShouldBroadcast
{
    use Dispatchable;

    /*
     * Ein veraltetes Live-Signal nicht wiederholen, das Polling
     * des Küchenmonitors gleicht ohnehin ab.
     */
    public int $tries = 1;

    /**
     * @param  array<int, int>  $stationIds
     */
    public function __construct(public array $stationIds)
    {
    }

    public function broadcastQueue(): string
    {
        return 'broadcasts';
    }

    public function broadcastOn(): Channel
    {
        return new Channel('production');
    }

    public function broadcastAs(): string
    {
        return 'production.changed';
    }

    /**
     * @return array{stationIds: array<int, int>}
     */
    public function broadcastWith(): array
    {
        return ['stationIds' => $this->stationIds];
    }
}
