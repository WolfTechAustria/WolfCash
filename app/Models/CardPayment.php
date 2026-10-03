<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardPayment extends Model
{
    public const MODE_SELECTED = 'selected';

    public const MODE_REMAINING = 'remaining';

    /*
     * Gestartet, Karte noch nicht abgebucht.
     */
    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELED = 'canceled';

    /*
     * Bei Stripe abgebucht, aber nicht verbucht – muss manuell
     * geprüft werden (siehe Log).
     */
    public const STATUS_REQUIRES_ATTENTION = 'requires_attention';

    protected $fillable = [
        'order_id',
        'mode',
        'selection',
        'invoice_recipient',
        'amount',
        'currency',
        'status',
        'stripe_payment_intent_id',
        'payment_id',
        'device_id',
        'user_id',
        'failure_message',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'selection' => 'array',
            'invoice_recipient' => 'array',
            'amount' => 'decimal:2',
            'completed_at' => 'datetime',
        ];
    }

    public function amountInCents(): int
    {
        return (int) round((float) $this->amount * 100);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
