<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends Model
{
    public const CASH = 'cash';

    public const CARD = 'card';

    public const VOUCHER = 'voucher';

    public const INVOICE = 'invoice';

    public const HOUSE = 'house';

    public static function methodLabel(?string $method): string
    {
        return match ($method) {
            self::CASH => 'Barzahlung',
            self::CARD => 'Kartenzahlung',
            self::VOUCHER => 'Bon/Gutschein',
            self::INVOICE => 'Rechnung',
            self::HOUSE => 'Auf Haus',
            default => (string) ($method ?? 'Unbekannt'),
        };
    }

    protected $fillable = [
        'order_id',
        'amount',
        'payment_method',
        'device_id',
        'user_id',
        'invoice_recipient_name',
        'invoice_recipient_address',
        'invoice_recipient_vat_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function cancellations(): HasMany
    {
        return $this->hasMany(
            OrderItemCancellation::class
        );
    }

    public function receiptPrintJob(): HasOne
    {
        return $this->hasOne(
            PrintJob::class
        )->where(
            'type',
            PrintJob::TYPE_RECEIPT
        );
    }
}
