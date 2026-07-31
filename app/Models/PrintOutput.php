<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrintOutput extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PRINTING = 'printing';
    public const STATUS_PRINTED = 'printed';
    public const STATUS_FAILED = 'failed';

    public const TYPE_PRODUCTION = 'production';

    public const TYPE_CANCELLATION = 'cancellation';

    protected $fillable = [
        'print_job_id',
        'order_item_id',
        'printer_id',
        'quantity',
        'type',
        'status',
        'payload',
        'printed_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'payload' => 'array',
            'printed_at' => 'datetime',
        ];
    }

    public function printJob(): BelongsTo
    {
        return $this->belongsTo(PrintJob::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function printer(): BelongsTo
    {
        return $this->belongsTo(Printer::class);
    }
}
