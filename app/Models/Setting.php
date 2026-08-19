<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    public const SELF_ORDERING_ENABLED =
        'self_ordering_enabled';

    public const RECEIPT_AUTOMATIC_PRINTING_ENABLED =
        'receipt_automatic_printing_enabled';

    public const RECEIPT_REPRINTING_ENABLED =
        'receipt_reprinting_enabled';

    protected $fillable = [
        'key',
        'value',
    ];

    public static function selfOrderingEnabled(): bool
    {
        return static::boolean(
            static::SELF_ORDERING_ENABLED,
            false
        );
    }

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
}
