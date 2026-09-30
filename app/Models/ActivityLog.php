<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * Audit-Eintrag: wer (Benutzer/Gerät) hat wann was getan.
 * Einträge werden ausschließlich über den ActivityLogger angelegt
 * und sind danach unveränderlich.
 */
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    public const ORDER_BOOKED = 'order.booked';

    public const ITEM_CANCELLED = 'order_item.cancelled';

    public const PAYMENT_CREATED = 'payment.created';

    public const DAILY_CLOSING_CREATED = 'daily_closing.created';

    public const DEVICE_APPROVED = 'device.approved';

    public const DEVICE_BLOCKED = 'device.blocked';

    public const DEVICE_RENAMED = 'device.renamed';

    public const PRODUCT_CREATED = 'product.created';

    public const PRODUCT_UPDATED = 'product.updated';

    public const PRODUCT_DELETED = 'product.deleted';

    public const SETTINGS_UPDATED = 'settings.updated';

    public const SYSTEM_RESET = 'system.reset';

    public const LOGIN = 'auth.login';

    public const LOGIN_FAILED = 'auth.failed';

    public const LOGOUT = 'auth.logout';

    /**
     * Anzeigenamen je Ereignistyp, zugleich die Filterliste im Admin.
     *
     * @return array<string, string>
     */
    public static function eventLabels(): array
    {
        return [
            self::ORDER_BOOKED => 'Bonierung',
            self::ITEM_CANCELLED => 'Storno',
            self::PAYMENT_CREATED => 'Zahlung',
            self::DAILY_CLOSING_CREATED => 'Tagesabschluss',
            self::DEVICE_APPROVED => 'Gerät freigegeben',
            self::DEVICE_BLOCKED => 'Gerät gesperrt',
            self::DEVICE_RENAMED => 'Gerät umbenannt',
            self::PRODUCT_CREATED => 'Produkt angelegt',
            self::PRODUCT_UPDATED => 'Produkt geändert',
            self::PRODUCT_DELETED => 'Produkt gelöscht',
            self::SETTINGS_UPDATED => 'Einstellungen',
            self::SYSTEM_RESET => 'System-Reset',
            self::LOGIN => 'Anmeldung',
            self::LOGIN_FAILED => 'Anmeldung fehlgeschlagen',
            self::LOGOUT => 'Abmeldung',
        ];
    }

    public static function eventLabel(string $event): string
    {
        return self::eventLabels()[$event] ?? $event;
    }

    protected $fillable = [
        'event',
        'description',
        'subject_type',
        'subject_id',
        'user_id',
        'user_name',
        'device_id',
        'device_name',
        'ip_address',
        'properties',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Audit-Einträge sind unveränderlich.');
        });

        static::deleting(function (): void {
            throw new LogicException('Audit-Einträge sind unveränderlich.');
        });
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function getActorLabelAttribute(): string
    {
        return collect([$this->user_name, $this->device_name])
            ->filter()
            ->implode(' · ') ?: 'System';
    }
}
