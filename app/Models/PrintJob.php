<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
        'printer_id',
        'type',
        'status',
        'payload',
        'printed_at',
        'error_message',
        'production_station_id',
    ];

    protected $casts = [
        'payload' => 'array',
        'printed_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function printer()
    {
        return $this->belongsTo(Printer::class);
    }

    public function productionStation()
    {
        return $this->belongsTo(
            ProductionStation::class
        );
    }
}
