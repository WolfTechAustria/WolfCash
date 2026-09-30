<?php

namespace App\Services;

use App\Events\ProductionBoardChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Sammelt Änderungen an offenen Produktionsbons und meldet sie
 * gebündelt nach dem Commit an die Küchenmonitore.
 */
class ProductionBoard
{
    /**
     * Noch nicht versendete Stations-IDs, gesammelt bis zum Commit,
     * damit z. B. ein Bonieren für mehrere Stationen nur ein
     * einziges Live-Signal erzeugt.
     *
     * @var array<int, int>
     */
    private static array $pendingStations = [];

    public static function changed(?int $stationId): void
    {
        /*
         * Bons ohne Station (z. B. stationäre Bestellbons) erscheinen
         * nur in der Ansicht „Alle“ – Station 0 steht dafür.
         */
        $stationId = (int) $stationId;

        self::$pendingStations[$stationId] = $stationId;

        /*
         * Jeder Commit versendet alles bis dahin Gesammelte; spätere
         * Callbacks derselben Transaktion finden die Liste leer vor.
         */
        DB::afterCommit(function (): void {
            if (self::$pendingStations === []) {
                return;
            }

            $stationIds = array_values(self::$pendingStations);
            self::$pendingStations = [];

            try {
                ProductionBoardChanged::dispatch($stationIds);
            } catch (\Throwable $e) {
                Log::warning('Küchenmonitor-Broadcast fehlgeschlagen: '.$e->getMessage());
            }
        });
    }
}
