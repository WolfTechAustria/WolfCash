<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Table extends Model
{
    protected $fillable = [
        'number',
        'name',
        'status',
        'self_order_enabled',
    ];

    protected function casts(): array
    {
        return [
            'self_order_enabled' => 'boolean',
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

    public function tableOrderSessions()
    {
        return $this->hasMany(
            TableOrderSession::class
        );
    }

    public function selfOrders()
    {
        return $this->hasMany(
            SelfOrder::class
        );
    }
}
