<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyClosing extends Model
{
    protected $fillable = [
        'business_date',
        'closed_at',
        'closed_by',
        'orders_total',
        'orders_paid',
        'orders_cancelled',
        'gross_amount',
        'cancelled_amount',
        'payable_amount',
        'paid_amount',
        'cash_amount',
        'card_amount',
        'payments_count',
        'cancellations_count',
        'cancelled_quantity',
        'snapshot',
    ];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'closed_at' => 'datetime',

            'orders_total' => 'integer',
            'orders_paid' => 'integer',
            'orders_cancelled' => 'integer',

            'gross_amount' => 'decimal:2',
            'cancelled_amount' => 'decimal:2',
            'payable_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'cash_amount' => 'decimal:2',
            'card_amount' => 'decimal:2',

            'payments_count' => 'integer',
            'cancellations_count' => 'integer',
            'cancelled_quantity' => 'integer',

            'snapshot' => 'array',
        ];
    }

    public function closedByUser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'closed_by'
        );
    }
}
