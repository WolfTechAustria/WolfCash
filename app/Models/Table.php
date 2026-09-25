<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\TableOrderSession;

class Table extends Model
{
    protected $fillable = [
        'number',
        'name',
        'status',
        'self_order_enabled',
        'is_stationary',
    ];

    protected function casts(): array
    {
        return [
            'self_order_enabled' => 'boolean',
            'is_stationary' => 'boolean',
        ];
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
