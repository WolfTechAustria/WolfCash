<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    public const RECEIPT_AUTOMATIC_PRINTING_ENABLED =
        'receipt_automatic_printing_enabled';

    public const RECEIPT_REPRINTING_ENABLED =
        'receipt_reprinting_enabled';

    public const SELF_ORDERING_ENABLED =
        'self_ordering_enabled';

    public const SELF_ORDERING_TITLE =
        'self_ordering_title';

    public const SELF_ORDERING_SUBTITLE =
        'self_ordering_subtitle';

    public const CARD_PAYMENT_ENABLED =
        'card_payment_enabled';

    public const VOUCHER_PAYMENT_ENABLED =
        'voucher_payment_enabled';

    public const RECEIPT_TITLE =
        'receipt_title';

    public const RECEIPT_INTRO =
        'receipt_intro';

    public const RECEIPT_PRINTER_ID =
        'receipt_printer_id';

    public const STATIONARY_PRINTER_ID =
        'stationary_printer_id';

    /**
     * Alle bekannten Schlüssel, z. B. für den System-Reset.
     *
     * @return list<string>
     */
    public static function keys(): array
    {
        return [
            static::RECEIPT_AUTOMATIC_PRINTING_ENABLED,
            static::RECEIPT_REPRINTING_ENABLED,
            static::SELF_ORDERING_ENABLED,
            static::SELF_ORDERING_TITLE,
            static::SELF_ORDERING_SUBTITLE,
            static::CARD_PAYMENT_ENABLED,
            static::VOUCHER_PAYMENT_ENABLED,
            static::RECEIPT_TITLE,
            static::RECEIPT_INTRO,
            static::RECEIPT_PRINTER_ID,
            static::STATIONARY_PRINTER_ID,
        ];
    }

    protected $fillable = [
        'key',
        'value',
    ];

    public static function valueOf(
        string $key,
        mixed $default = null
    ): mixed {
        return Cache::remember(
            'setting:'.$key,
            now()->addHour(),
            fn () => static::query()
                ->where('key', $key)
                ->value('value') ?? $default
        );
    }

    public static function boolean(
        string $key,
        bool $default = false
    ): bool {
        return filter_var(
            static::valueOf(
                $key,
                $default ? '1' : '0'
            ),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    public static function putValue(
        string $key,
        mixed $value
    ): void {
        static::query()->updateOrCreate(
            [
                'key' => $key,
            ],
            [
                'value' => match (true) {
                    is_bool($value) =>
                    $value ? '1' : '0',

                    $value === null =>
                    null,

                    default =>
                    (string) $value,
                },
            ]
        );

        Cache::forget('setting:'.$key);
    }

    public static function automaticReceiptPrintingEnabled(): bool
    {
        return static::boolean(
            static::RECEIPT_AUTOMATIC_PRINTING_ENABLED,
            true
        );
    }

    public static function receiptReprintingEnabled(): bool
    {
        return static::boolean(
            static::RECEIPT_REPRINTING_ENABLED,
            true
        );
    }

    public static function cardPaymentEnabled(): bool
    {
        return static::boolean(
            static::CARD_PAYMENT_ENABLED,
            true
        );
    }

    public static function receiptTitle(): string
    {
        return trim((string) static::valueOf(static::RECEIPT_TITLE, ''));
    }

    public static function receiptIntro(): string
    {
        return trim((string) static::valueOf(static::RECEIPT_INTRO, ''));
    }

    /*
     * Im Admin gewählter Drucker; die .env-Werte bleiben als Rückfall
     * für bestehende Installationen erhalten.
     */
    public static function receiptPrinterId(): int
    {
        return (int) (
            static::valueOf(static::RECEIPT_PRINTER_ID)
            ?: config('printing.receipt_printer_id', 0)
        );
    }

    public static function stationaryPrinterId(): int
    {
        return (int) (
            static::valueOf(static::STATIONARY_PRINTER_ID)
            ?: config('printing.stationary_order_printer_id', 0)
        );
    }

    public static function voucherPaymentEnabled(): bool
    {
        return static::boolean(
            static::VOUCHER_PAYMENT_ENABLED,
            true
        );
    }

    public static function selfOrderingEnabled(): bool
    {
        return static::boolean(
            static::SELF_ORDERING_ENABLED,
            false
        );
    }

    public static function selfOrderingTitle(): string
    {
        return (string) static::valueOf(
            static::SELF_ORDERING_TITLE,
            'Direkt bestellen'
        );
    }

    public static function selfOrderingSubtitle(): string
    {
        return (string) static::valueOf(
            static::SELF_ORDERING_SUBTITLE,
            'Scannen · Bestellen · Bezahlen'
        );
    }
}
