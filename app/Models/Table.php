<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\TableOrderSession;
use App\Support\RowLock;

class Table extends Model
{
    protected $fillable = [
        'number',
        'name',
        'status',
        'self_order_enabled',
        'is_stationary',
        'printer_id',
    ];

    protected function casts(): array
    {
        return [
            'self_order_enabled' => 'boolean',
            'is_stationary' => 'boolean',
        ];
    }

    /**
     * Sperrt den Tisch für Bonieren, Kassieren und Storno, damit parallele
     * Kassen am selben Tisch nacheinander statt gegeneinander laufen.
     * Nur innerhalb einer Transaktion und immer als erste Sperre.
     */
    public static function lockForBooking(int $tableId): self
    {
        return RowLock::forUpdate(static::query()->whereKey($tableId))
            ->firstOrFail();
    }

    /*
     * Eigener Drucker einer stationären Kassa für Bons und Zahlungsbelege.
     */
    public function printer(): BelongsTo
    {
        return $this->belongsTo(Printer::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function openOrder()
    {
        return $this->hasOne(Order::class)
            ->where('status', Order::STATUS_OPEN);
    }

    public function tableOrderSessions(): HasMany
    {
        return $this->hasMany(
            TableOrderSession::class
        );
    }

    public function activeTableOrderSession(): HasOne
    {
        return $this->hasOne(
            TableOrderSession::class
        )
            ->where('active', true)
            ->whereNotNull('token')
            ->latestOfMany();
    }

    public function selfOrders()
    {
        return $this->hasMany(
            SelfOrder::class
        );
    }
}
