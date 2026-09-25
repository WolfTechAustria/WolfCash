<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Serverseitige Reservierung einer Warenkorbmenge.
 *
 * Solange eine Reservierung aktiv ist, wird ihre Menge vom
 * angezeigten Bestand (available_quantity) abgezogen.
 */
class ProductReservation extends Model
{
    protected $fillable = [
        'product_id',
        'holder',
        'self_order_id',
        'quantity',
        'expires_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'expires_at' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function selfOrder()
    {
        return $this->belongsTo(SelfOrder::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }
}
