<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrintJob extends Model
{
    public const TYPE_PRODUCTION = 'production';

    public const TYPE_RECEIPT = 'receipt';

    public const TYPE_STATIONARY_ORDER = 'stationary_order';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PRINTING = 'printing';

    public const STATUS_PRINTED = 'printed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'order_id',
        'payment_id',
        'printer_id',
        'type',
        'status',
        'payload',
        'printed_at',
        'error_message',
        'production_station_id',
        'production_completed_at',
        'ready_to_print',
        'show_on_monitor',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'ready_to_print' => 'boolean',
            'show_on_monitor' => 'boolean',
            'printed_at' => 'datetime',
            'production_completed_at' => 'datetime',
        ];
    }

    /**
     * Offene Bons, an denen die Küche noch etwas tun muss.
     */
    public function scopeOpenOnMonitor(Builder $query): Builder
    {
        return $query
            ->where('show_on_monitor', true)
            ->whereNull('production_completed_at');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function printer(): BelongsTo
    {
        return $this->belongsTo(Printer::class);
    }

    public function productionStation(): BelongsTo
    {
        return $this->belongsTo(
            ProductionStation::class
        );
    }

    public function outputs(): HasMany
    {
        return $this->hasMany(PrintOutput::class);
    }

    /**
     * Status aus den Einzelausdrucken ableiten.
     *
     * Zahlungsbelege und Produktionsbons mit Auslöser "pro Position"
     * werden nicht über ProcessPrintJob, sondern als PrintOutputs
     * gedruckt. Ohne diesen Abgleich bliebe der Job auf "pending".
     * Stornobons hängen am Originaljob und zählen hier nicht mit.
     */
    public function syncStatusFromOutputs(): void
    {
        $outputs = $this->outputs()
            ->where('type', '!=', PrintOutput::TYPE_CANCELLATION)
            ->get();

        if ($outputs->isEmpty()) {
            return;
        }

        $printed = $outputs->where('status', PrintOutput::STATUS_PRINTED);
        $failed = $outputs->where('status', PrintOutput::STATUS_FAILED);

        $open = $outputs->whereIn('status', [
            PrintOutput::STATUS_PENDING,
            PrintOutput::STATUS_PRINTING,
        ]);

        /*
         * Beleg: ein erfolgreicher Ausdruck genügt, ein später
         * fehlgeschlagener Nachdruck macht ihn nicht ungültig.
         * Produktion: erst wenn alles fertig und gedruckt ist.
         */
        $complete = $this->type === self::TYPE_RECEIPT
            ? $printed->isNotEmpty()
            : $this->production_completed_at !== null
                && $failed->isEmpty()
                && $open->isEmpty();

        if ($complete) {
            $this->update([
                'status' => self::STATUS_PRINTED,
                'printed_at' => $printed->max('printed_at') ?? now(),
                'error_message' => null,
            ]);

            return;
        }

        if ($failed->isNotEmpty()) {
            $this->update([
                'status' => self::STATUS_FAILED,
                'error_message' => $failed->sortByDesc('updated_at')->first()->error_message,
            ]);

            return;
        }

        $this->update([
            'status' => $outputs->contains('status', PrintOutput::STATUS_PRINTING)
                ? self::STATUS_PRINTING
                : self::STATUS_PENDING,
            'error_message' => null,
        ]);
    }
}
