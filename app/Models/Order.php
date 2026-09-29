<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'table_id',
        'status',
        'total',
        'source',
    ];

    public const SOURCE_POS = 'pos';

    public const SOURCE_SELF_ORDER = 'self_order';

    public const SOURCE_STATIONARY = 'stationary';

    public const STATUS_OPEN = 'open';
    public const STATUS_SENT = 'sent';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';

    public function table()
    {
        return $this->belongsTo(Table::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function printJobs()
    {
        return $this->hasMany(PrintJob::class);
    }

    public function recalculateTotal(): void
    {
        $total = $this->items()
            ->selectRaw(
                'COALESCE(SUM((quantity - cancelled_quantity) * price), 0) AS total'
            )
            ->value('total');

        $this->update([
            'total' => $total,
        ]);
    }

    /**
     * Schließt die Bestellung, sobald keine unbezahlte Menge mehr offen
     * ist, und gibt den Tisch frei. Anhand der Datenbank statt geladener
     * Daten geprüft und nur unter gesperrtem Tisch aufrufen (siehe
     * Table::lockForBooking), sonst übersehen parallele Kassen einander.
     */
    public function closeIfSettled(): bool
    {
        if ($this->status !== self::STATUS_OPEN) {
            return false;
        }

        $openAmount = (float) $this->items()
            ->whereNull('paid_at')
            ->selectRaw(
                'COALESCE(SUM((quantity - cancelled_quantity) * price), 0) AS open_amount'
            )
            ->value('open_amount');

        if ($openAmount > 0.009) {
            return false;
        }

        /*
         * Mit Zahlungen regulär abgerechnet (Rest ggf. storniert),
         * ohne Zahlung ein vollständiges Storno.
         */
        $this->update([
            'status' => $this->payments()->exists()
                ? self::STATUS_PAID
                : self::STATUS_CANCELLED,
        ]);

        $this->table()->update([
            'status' => 'free',
        ]);

        return true;
    }
}
