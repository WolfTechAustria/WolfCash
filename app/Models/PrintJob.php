<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrintJob extends Model
{
    public const TYPE_PRODUCTION = 'production';

    public const TYPE_RECEIPT = 'receipt';

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
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'ready_to_print' => 'boolean',
            'printed_at' => 'datetime',
            'production_completed_at' => 'datetime',
        ];
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
}
