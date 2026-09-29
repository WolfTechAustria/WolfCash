<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'price',
        'status',
        'note',
        'paid_at',
        'payment_id',
        'device_id',
        'user_id',
        'production_status',
        'production_completed_quantity',
        'production_printed_quantity',
        'cancelled_quantity',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_PREPARED = 'prepared';
    public const STATUS_SERVED = 'served';

    public const PRODUCTION_PENDING = 'pending';

    public const PRODUCTION_PROGRESS = 'in_progress';

    public const PRODUCTION_DONE = 'done';

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Gerät, von dem die Position boniert wurde.
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * Angemeldeter Benutzer (z. B. Admin über "Kasse öffnen").
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Anzeigename der Herkunft: Gerät, sonst Benutzer, sonst
     * Self Order. Null bei Altdaten ohne Zuordnung.
     */
    public function getOriginLabelAttribute(): ?string
    {
        if ($this->device) {
            return $this->device->name;
        }

        if ($this->user) {
            return $this->user->name;
        }

        if ($this->order?->source === Order::SOURCE_SELF_ORDER) {
            return 'Self Order';
        }

        return null;
    }

    protected function casts(): array {
        return [
            'paid_at' => 'datetime',
            'production_completed_quantity' => 'integer',
            'production_printed_quantity' => 'integer',
            'quantity' => 'integer',
            'cancelled_quantity' => 'integer',
            'cancelled_at' => 'datetime',
        ];
    }

    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'cancelled_by'
        );
    }

    public function getOpenQuantityAttribute(): int
    {
        return max(
            0,
            $this->quantity - $this->cancelled_quantity
        );
    }

    public function getIsFullyCancelledAttribute(): bool
    {
        return $this->cancelled_quantity >= $this->quantity;
    }

    public function cancellations()
    {
        return $this->hasMany(
            OrderItemCancellation::class
        );
    }
}
