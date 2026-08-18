<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SelfOrder extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_AWAITING_PAYMENT =
        'awaiting_payment';

    public const STATUS_PAYMENT_PROCESSING =
        'payment_processing';

    public const STATUS_PAID =
        'paid';

    public const STATUS_SUBMITTED =
        'submitted';

    public const STATUS_CANCELLED =
        'cancelled';

    public const STATUS_EXPIRED =
        'expired';

    protected $fillable = [
        'table_order_session_id',
        'table_id',
        'order_id',
        'status',
        'amount',
        'currency',
        'payment_provider',
        'provider_payment_id',
        'paid_at',
        'submitted_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'submitted_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function tableOrderSession(): BelongsTo
    {
        return $this->belongsTo(
            TableOrderSession::class
        );
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SelfOrderItem::class);
    }

    public function recalculateAmount(): void
    {
        $amount = $this->items()
            ->selectRaw(
                'COALESCE(SUM(quantity * unit_price), 0) AS amount'
            )
            ->value('amount');

        $this->update([
            'amount' => $amount,
        ]);
    }
}
