<?php

namespace App\Support;

use Illuminate\Contracts\Database\Query\Builder as BuilderContract;

/**
 * Zeilensperre für Aktualisierungen, die keine Schlüssel ändern.
 *
 * PostgreSQL setzt beim Anlegen von Zeilen mit Fremdschlüssel (z. B.
 * order_items -> products, orders -> tables) automatisch FOR KEY SHARE
 * auf die referenzierte Zeile. FOR UPDATE kollidiert damit und führte
 * unter Last zu Deadlocks; FOR NO KEY UPDATE serialisiert konkurrierende
 * Buchungen genauso, blockiert aber keine Fremdschlüssel-Prüfungen.
 *
 * Feste Sperr-Reihenfolge im System: Tisch → Positionen → Produkte.
 */
class RowLock
{
    /**
     * @template T of BuilderContract
     *
     * @param  T  $query
     * @return T
     */
    public static function forUpdate(BuilderContract $query): BuilderContract
    {
        return $query->getConnection()->getDriverName() === 'pgsql'
            ? $query->lock('for no key update')
            : $query->lockForUpdate();
    }
}
